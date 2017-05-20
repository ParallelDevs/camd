<?php
/**
 * @file
 * Base feature context for AHS.
 */

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Driver\BrowserKitDriver;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Define application features from the specific context.
 */
class FeatureContext extends RawDrupalContext implements SnippetAcceptingContext {

  /**
   * Tracks the current scenario.
   *
   * @var Behat\Gherkin\Node\ScenarioInterface
   */
  protected $currentScenario;

  /**
   * Initializes context.
   *
   * @param string $screenshot_dir
   *   The screenshot directory full path.
   */
  public function __construct($screenshot_dir = '') {
    if (!empty($screenshot_dir)) {
      $this->screenshotDir = $screenshot_dir;
    }
  }

  /**
   * @BeforeScenario
   */
  public function setUpTestEnvironment(BeforeScenarioScope $scope) {
    /** @var Behat\Behat\Context\Environment\InitializedContextEnvironment */
    $environment = $scope->getEnvironment();

    // Track current scenario.
    $this->currentScenario = $scope->getScenario();
  }

  /**
   * Get a list of UIDs.
   *
   * @return
   *   An array of numeric UIDs of users created by Given... steps during this scenario.
   */
  public function getUIDs() {
    $uids = array();
    foreach ($this->users as $user) {
      $uids[] = $user->uid;
    }
    return $uids;
  }

  /**
   * Get a list of nids.
   *
   * @return
   *   An array of numeric NIDs of Nodes created by Given... steps during this scenario.
   */
  public function getNIDs() {
    $nids = array();
    foreach ($this->nodes as $node) {
      $nids[] = $node->nid;
    }

    return $nids;
  }

  /**
   * Checks on the content type header response to the latest request.
   *
   * @param string $value
   *   Expected value of the content-type header.
   *
   * @Then /^(?:the )?response MIME type should be "([^"]*)"$/
   */
  public function theContentTypeHeaderShouldBe($value) {
    $type = $this->getSession()->getResponseHeader('Content-Type');
    if ($value && $value == $type) {
      return;
    }

    throw new ExpectationException(
      "Content-Type is incorrect, found '$type'.",
      $this->getSession()->getDriver()
    );
  }

  /**
   * Checks on the content type header response to the latest request.
   *
   * @param string $value
   *   Expected value of the content-type header.
   *
   * @Then /^I wait for (\d+) seconds?$/
   */
  public function iWaitForSeconds($value) {
    sleep($value);
  }

  /**
   * Checks, whether the header name is not equal to given text
   *
   * @Then the header :name should not equal :value
   */
  public function theHeaderShouldNotBeEqualTo($name, $value) {
    $actual = $this->getSession()->getResponseHeader($name);
    if (strtolower($value) == strtolower($actual)) {
      throw new ExpectationException(
        "The header '$name' is equal to '$actual'",
        $this->getSession()->getDriver()
      );
    }
  }

  /**
   * Issue a ban request to varnish for everything.
   *
   * @Given varnish has nothing cached
   */
  public function varnishCacheIsEmpty() {
    $ch = curl_init($this->getMinkParameter('base_url'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGEALL');
    if (!curl_exec($ch)) {
      throw new Exception('Unable to purge varnish. Ensure it is configured to accept the PURGEALL request method. Error: ' . curl_errno($ch) . ' ' . curl_error($ch));
    }
    else {
      print 'PURGEALL issued for ' . $this->getMinkParameter('base_url') . PHP_EOL;
    }
  }

  /**
   * Update the last node to have a new title.
   *
   * @When the title of the last node created is set to :title
   */
  public function setTitleOfLastNodeCreatedTo($title) {
    $node = $this->nodes[count($this->nodes) - 1];
    /* @var $ent \Drupal\node\NodeInterface */
    $ent = \Drupal::entityTypeManager()->getStorage('node')->load($node->nid);
    $ent->setTitle($title);
    if ($ent->save() != SAVED_UPDATED) {
      throw new Exception('Could not update title of the last node: ' . $node->nid);
    }
    else {
      print 'Title of node ' . $node->nid . ' set to ' . $title;
    }
  }

  /**
   * Process the cache queue.
   *
   * @When cache queue processing runs
   */
  public function processCacheQueue() {
    /* @var $service \Drupal\purge\Plugin\Purge\Queue\QueueService */
    $service = \Drupal::service('purge.queue');
    // This is required because the purge queue would otherwise act only at
    // termination of the scenario to insert the invalidations into the queue.
    $service->commit();
    $this->iRunDrush('pqw');
  }

  /**
   * Run drush command.
   *
   * @Then I run drush :cmd
   */
  public function iRunDrush($cmd) {
    /** @var \Drupal\Driver\DrushDriver $drush */
    $drush = $this->getDriver('drush');
    print $drush->$cmd();
  }

  /**
   * Follow redirect instructions.
   *
   * @Then /^I (?:am|should be) redirected$/
   */
  public function iAmRedirected() {
    $headers = $this->getSession()->getResponseHeaders();
    if (empty($headers['Location']) && empty($headers['location'])) {
      throw new \RuntimeException('The response should contain a "Location" header');
    }
    $client = $this->getClient();
    $client->followRedirects(true);
    $client->followRedirect();
  }

  /**
   * Check that the current page response status is in the specified range.
   *
   * A range would look like 1xx, 2xx, 3xx, 4xx, or 5xx.
   *
   * @param string $mask
   *   An HTTP status code in mask format.
   *
   * @Then the response status code should be in the :mask range.
   *
   * @throws ExpectationException
   */
  public function hasStatusRange($mask) {
    $actual = $this->getSession()->getStatusCode();

    if (substr($mask, 0, 1) != substr($actual, 0, 1)) {
      $message = sprintf('Response code range %s expected, %s found', $mask, $actual);
      throw new ExpectationException($message, $this->getSession()->getDriver());
    }
  }

  /**
   * Returns current active mink session.
   *
   * @return  \Symfony\Component\BrowserKit\Client
   *
   * @throws  \Behat\Mink\Exception\UnsupportedDriverActionException
   */
  protected function getClient() {
    $driver = $this->getSession()->getDriver();
    if (!$driver instanceof BrowserKitDriver) {
      $message = 'This step is only supported by the browserkit drivers';
      throw new UnsupportedDriverActionException($message, $driver);
    }
    return $driver->getClient();
  }

}
