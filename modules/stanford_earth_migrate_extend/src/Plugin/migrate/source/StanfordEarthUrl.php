<?php

namespace Drupal\stanford_earth_migrate_extend\Plugin\migrate\source;

use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\migrate\Row;

/**
 * Source plugin for retrieving data via URLs.
 *
 * @MigrateSource(
 *   id = "stanford_earth_url"
 * )
 */
class StanfordEarthUrl extends Url {

  /**
   * {@inheritdoc}
   *
   * The migration iterates over rows returned by the source plugin. This
   * method determines the next row which will be processed and imported into
   * the system.
   *
   * The method tracks the source and destination IDs using the ID map plugin.
   *
   * This also takes care about highwater support. Highwater allows to reimport
   * rows from a previous migration run, which got changed in the meantime.
   * This is done by specifying a highwater field, which is compared with the
   * last time, the migration got executed (originalHighWater).
   */
  public function next() {

    $this->currentSourceIds = NULL;
    $this->currentRow = NULL;

    // In order to find the next row we want to process, we ask the source
    // plugin for the next possible row.
    while (!isset($this->currentRow) && $this->getIterator()->valid()) {

      $row_data = $this->getIterator()->current() + $this->configuration;

      // Start new code - ksharp - 2018-07-03.
      // We need to know the URL from which the current row is gotten.
      $plugin = $this->getDataParserPlugin();
      $curUrl = NULL;
      if (method_exists($plugin, "getActiveUrl")) {
        $activeUrl = $plugin->getActiveUrl();
        if (!empty($activeUrl) && !empty($row_data['urls'][$activeUrl])) {
          $curUrl = $row_data['urls'][$activeUrl];
        }
      }
      if (!empty($curUrl)) {
        $row_data['current_feed_url'] = $curUrl;
      }
      // End new code - ksharp.
      //
      $this->fetchNextRow();
      $row = new Row($row_data, $this->migration->getSourcePlugin()
        ->getIds(), $this->migration->getDestinationIds());

      // Populate the source key for this row.
      $this->currentSourceIds = $row->getSourceIdValues();

      // Pick up the existing map row, if any, unless fetchNextRow() did it.
      if (!$this->mapRowAdded && ($id_map = $this->idMap->getRowBySource($this->currentSourceIds))) {
        $row->setIdMap($id_map);
      }

      // Clear any previous messages for this row before potentially adding
      // new ones.
      if (!empty($this->currentSourceIds)) {
        $this->idMap->delete($this->currentSourceIds, TRUE);
      }

      // Preparing the row gives source plugins the chance to skip.
      if ($this->prepareRow($row) === FALSE) {
        continue;
      }

      // Check whether the row needs processing.
      // 1. This row has not been imported yet.
      // 2. Explicitly set to update.
      // 3. The row is newer than the current highwater mark.
      // 4. If no such property exists then try by checking the hash of the row.
      if (!$row->getIdMap() || $row->needsUpdate() || $this->aboveHighwater($row) || $this->rowChanged($row)) {
        $this->currentRow = $row->freezeSource();
      }

      if ($this->getHighWaterProperty()) {
        $this->saveHighWater($row->getSourceProperty($this->highWaterProperty['name']));
      }
    }
  }

}
