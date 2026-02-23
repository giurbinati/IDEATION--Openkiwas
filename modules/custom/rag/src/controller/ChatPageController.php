<?php

namespace Drupal\rag\Controller;

use Drupal\Core\Controller\ControllerBase;

class ChatPageController extends ControllerBase
{

  public function page(): array
  {
    return [
      '#markup' => '
      <h1 class="rag-page-title">OpenKIWAS Chatbot</h1>
      <div id="openkiwas-chat" data-endpoint="/openkiwas-final-version/rag/chat"></div>',
      '#attached' => [
        'library' => ['rag/chat_ui'],
      ],
    ];
  }


}
