<?php

/**
 * @file
 * Contains Drupal\apimon\Access\PostWeightAccessCheck.
 */

namespace Drupal\apimon\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Request;

/**
 * Access check for user registration routes.
 */
class PostWeightAccessCheck implements AccessInterface {

  /**
   * Checks access.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Request $request) {
    if ($request->getMethod() != 'POST') {
      return AccessResult::forbidden();
    }
    $headers = $request->headers->all();
    if (empty($headers['x-user-weight']) || empty($headers['x-user-weight'][0])) {
      return AccessResult::forbidden();
    }
    // Check if the actual path is allowed for the actual "api-key".
    $user = $request->attributes->get('user');
    $key = $headers['x-user-weight'][0];
    if ((bool) Database::getConnection()->query("SELECT * FROM {apimon_keys} u WHERE u.uid = :uid AND u.user_key = :user_key", [
      ':uid' => $user,
      ':user_key' => $key
    ])->fetchField()) {
      return AccessResult::allowed();
    }
    // If the user did not supply the key for the user in the route, then they
    // are denied access.
    return AccessResult::forbidden();
  }

}
