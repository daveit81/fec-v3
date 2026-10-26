<?php
defined('_JEXEC') or die;
class FecApiControllerBonus extends FecApiController {
  public function draft() {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);
      JFactory::getApplication()->close();
    }

    JLoader::register('FecApiAuthHelper', JPATH_COMPONENT.'/helpers/auth.php');
    if (!FecApiAuthHelper::checkBearerToken()) {
      http_response_code(403);
      echo json_encode(['ok'=>false,'error'=>'forbidden']);
      JFactory::getApplication()->close();
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
      http_response_code(400);
      echo json_encode(['ok'=>false,'error'=>'invalid_json']);
      JFactory::getApplication()->close();
    }

    $model = $this->getModel('Bonus');
    $result = $model->upsertDraft($data);

    echo json_encode([
      'ok'=>true,
      'action'=>$result['action'],
      'bid'=>$result['bid'],
      'status'=>'pending'
    ]);
    JFactory::getApplication()->close();
  }
}
