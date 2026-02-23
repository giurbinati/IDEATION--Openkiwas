<?php

namespace Drupal\rag\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Exception\RequestException;


class ChatProxyController extends ControllerBase {

  public function chat(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE) ?? [];

    $question = (string) ($payload['question'] ?? '');
    $history = $payload['history'] ?? [];

    if (trim($question) === '') {
      return new JsonResponse(['error' => 'Missing question'], 400);
    }

    // ⚠️ Backend URL (se FastAPI è su un'altra macchina, cambia qui)
    $backend_url = 'http://127.0.0.1:8000/chat';

    try {
      $client = \Drupal::httpClient();
      $res = $client->post($backend_url, [
        'json' => [
          'question' => $question,
          'history' => $history,
        ],
        'timeout' => 60,
      ]);

      $data = json_decode($res->getBody()->getContents(), TRUE);
      return new JsonResponse($data, 200);
    }
    catch (RequestException $e) {
      $msg = $e->getMessage();
      // Se il backend risponde con body errore, proviamo a leggerlo
      if ($e->hasResponse()) {
        $msg = (string) $e->getResponse()->getBody();
      }
      return new JsonResponse(['error' => $msg], 502);
    }
  }

}