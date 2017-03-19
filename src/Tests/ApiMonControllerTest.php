<?php

/**
 * @file
 * Contains Drupal\apimon\Tests\UserWeightControllerTest.
 */

namespace Drupal\apimon\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\Core\Database\Database;

/**
 * Provides automated tests for the apimon module.
 *
 * @group apimon
 */
class UserWeightControllerTest extends WebTestBase {
  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('apimon');
  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => "apimon controller functionality",
      'description' => 'Test Unit for module apimon and controller.',
      'group' => 'Other',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->api_user = $this->drupalCreateUser(array('create own weights'));
  }

  /**
   * Tests apimon functionality.
   */
  public function testUserWeightController() {
    // Start by asserting we are denied access to the route used for POSTing
    // weights.
    $this->drupalPost('/user/1/weights_post', 'application/json', array());
    $this->assertResponse(403);
    // Check that we are able to POST on behalf of the $api_user.
    $path = sprintf('user/%d/weights_post', $this->api_user->id());
    $weight = rand(0, 100);
    $this->curlExec(array(
      CURLOPT_URL => $this->buildUrl($path),
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => json_encode(array('weight' => $weight)),
      CURLOPT_HTTPHEADER => array(
        'Accept: application/json',
        'Content-Type: application/json',
        'x-user-weight: abc',
      ),
    ));
    // We should still not be able to POST it, since the API key is not
    // generated yet.
    $this->assertResponse(403);
    // Log in, and generate the API key.
    $this->drupalLogin($this->api_user);
    $overview_path = sprintf('user/%d/weights', $this->api_user->id());
    $this->drupalGet($overview_path);
    // At this point we should have 0 weights registered.
    $weight_rows_selector = '//div[@class="view-content"]//tr';
    $this->assertTrue((1 > count($this->xpath($weight_rows_selector))));
    // Check that some API key is generated and therefore the response would be
    // good.
    $this->assertText('Your API key is');
    $key_row = Database::getConnection()->query("SELECT * FROM {apimon_keys} u WHERE u.uid = :uid", [
      ':uid' => $this->api_user->id(),
    ])->fetchObject();
    $key = $key_row->user_key;
    // Do an actual POST with the value.
    $response = $this->curlExec(array(
      CURLOPT_URL => $this->buildUrl($path),
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => json_encode(array('weight' => $weight)),
      CURLOPT_HTTPHEADER => array(
        'x-user-weight: ' . $key,
      ),
    ));
    // This should be response code 200.
    $this->assertResponse(200);
    // Take a note of what nid we posted. Probably 1, in this case.
    $nid = json_decode($response)->nid;
    // The visit that page, and verify that the weight is set to what we
    // expect.
    $this->drupalGet('node/' . $nid);
    // Check the value.
    $weight_values = $this->xpath('//div[@class="field field-node--field-user-weight field-name-field-user-weight field-type-float field-label-above"]//div[@class="field-item"]');
    $this->assertTrue($weight_values[0] == $weight);
    // ...and check that we have that weight on the user weight page.
    $this->drupalGet($overview_path);
    $this->assertTrue(1 == count($this->xpath($weight_rows_selector)));
  }

}
