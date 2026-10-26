<?php
defined('_JEXEC') or die;
class FecApiModelBonus extends JModel {
  public function upsertDraft($data) {
    $db = JFactory::getDbo();

    if (!isset($data['idempotency_key'], $data['brand'], $data['type'])) {
      throw new Exception('missing_required_fields');
    }

    $idk = $db->escape($data['idempotency_key'], true);

    $query = $db->getQuery(true)
      ->select('bid')
      ->from('#__bonuses')
      ->where('status=' . $db->quote('pending'))
      ->where('additional_infos LIKE ' . $db->quote('%[idem:' . $idk . ']%'))
      ->setLimit(1);

    $db->setQuery($query);
    $bid = (int)$db->loadResult();

    $fields = [
      'brand' => $data['brand'],
      'type' => $data['type'],
      'status' => 'pending',
      'additional_infos' => '[idem:' . $data['idempotency_key'] . ']'
    ];

    if ($bid) {
      $q = $db->getQuery(true)->update('#__bonuses');
      foreach ($fields as $k=>$v) {
        $q->set($db->quoteName($k) . '=' . $db->quote($v));
      }
      $q->where('bid='.(int)$bid);
      $db->setQuery($q)->execute();
      return ['action'=>'updated','bid'=>$bid];
    } else {
      $cols = array_map([$db,'quoteName'], array_keys($fields));
      $vals = array_map([$db,'quote'], array_values($fields));
      $q = $db->getQuery(true)
        ->insert('#__bonuses')
        ->columns($cols)
        ->values(implode(',',$vals));
      $db->setQuery($q)->execute();
      return ['action'=>'created','bid'=>$db->insertid()];
    }
  }
}
