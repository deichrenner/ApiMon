<?php

/**
 * @file
 * Contains Drupal\ApiMon\Controller\ApiMonController.
 */

namespace Drupal\ApiMon\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\FlattenException;


/**
 * Class ApiMonController.
 *
 * @package Drupal\ApiMon\Controller
 */
class ApiMonController extends ControllerBase {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('views.executable')
    );
  }
  /**
   * Construct the ApiMonController.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   * @param \Drupal\views\ViewExecutableFactory $views_factory
   *   A... views factory? Whatever that is...
   */
  public function __construct(Connection $database, ViewExecutableFactory $views_factory) {
    $this->database = $database;
    $this->views_factory = $views_factory;
  }
  /**
   * User tab page for displaying apimon info.
   *
   * @param \Drupal\user\UserInterface $user
   *   A Drupal user.
   *
   * @return string
   *   Return some markup.
   */
  public function weight(UserInterface $user) {
    // See if the user already has an API key.
    $user_key_object = $this->database->query("SELECT * FROM {apimon_keys} u WHERE u.uid = :uid", array(':uid' => $user->id()))->fetchObject();
    if (!$user_key_object) {
      // The user does not have a key. Generate one for them.
      $user_key = sha1(uniqid());
      // Insert it to the database.
      $this->database
        ->insert('apimon_keys')
        ->fields(array(
          'uid' => $user->id(),
          'user_key' => $user_key,
        ))
        ->execute();
    }
    else {
      $user_key = $user_key_object->user_key;
    }

    // Generate the URL which we should use in the CURL explaination.
    $post_url = Url::fromRoute('apimon.post_weights', [
      'user' => $user->id(),
    ], [
      'absolute' => TRUE,
    ])->toString();

    // Also get a view of the users weights.
    $view = entity_load('view', 'user_weights');

    // Add the variables to the render array and render from our template.
    return [
      '#api_key' => $user_key,
      '#user_view' => $this->views_factory->get($view)->preview(),
      '#post_url' => $post_url,
      '#theme' => 'apimon-user-page',
    ];
  }

  /**
   * POST handler for processing the weights.
   *
   * @param string $user
   *   The uid in the route.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return string
   *   A JSON encoded object containing the nid of the node.
   */
  public function post($user, Request $request) {
    // Get the value posted.
    $data = $request->getContent();
    if (!$data) {
      throw new AccessDeniedHttpException();
    }
    // Then see if it is JSON.
    if (!$json = json_decode($data)) {
      throw new AccessDeniedHttpException();
    }
    // If it does not include a weight, we don't want it.
    if (empty($json->weight)) {
      throw new AccessDeniedHttpException();
    }
    // Then create a node with the weight.
    $nid = NULL;
    try {
      $edit = [
        'uid' => $user,
        'type' => 'user_weight',
        'langcode' => 'en',
        'title' => $this->t('Weight logged at @date', [
          '@date' => format_date(time(), 'custom', 'd.m.Y H:i:s'),
        ]),
        'promote' => 0,
      ];
      $node = entity_create('node', $edit);
      $node->get('field_user_weight')->setValue(SafeMarkup::checkPlain($json->weight));
      $node->save();
      $nid = $node->id();
    }
    catch (Exception $e) {
      // We had a problem.
      throw new FlattenException($e, 500);
    }

    return new JsonResponse(array(
      'nid' => $nid,
    ));
  }

}
