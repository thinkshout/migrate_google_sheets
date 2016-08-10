<?php

namespace Drupal\migrate_google_sheets\Plugin\migrate_plus\data_parser;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;
use GuzzleHttp\Exception\RequestException;

/**
 * Obtain Google Sheet data for migration.
 *
 * @DataParser(
 *   id = "google_sheets",
 *   title = @Translation("Google Sheets")
 * )
 */
class GoogleSheets extends Json implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSourceData($url) {
    // Since we're being explicit about the data location, we can return the
    // array without calling getSourceIterator to get an iterator to find the
    // correct values.
    try {
      $response = $this->getDataFetcherPlugin()->getResponseContent($url);
      // The TRUE setting means decode the response into an associative array.
      $array = json_decode($response, TRUE);

      // For Google Sheets, the actual row data lives under feed->entry.
      if (isset($array['feed']) && isset($array['feed']['entry'])) {
        $array = $array['feed']['entry'];
      }
      else {
        $array = [];
      }

      return $array;
    }
    catch (RequestException $e) {
      throw new MigrateException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow() {
    $current = $this->iterator->current();
    if ($current) {
      foreach ($this->fieldSelectors() as $field_name => $selector) {
        // Actual values are stored in gsx$<field>['$t'].
        $this->currentItem[$field_name] = $current['gsx$' . $selector]['$t'];
      }
      $this->iterator->next();
    }
  }

}
