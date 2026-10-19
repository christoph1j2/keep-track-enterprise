<?php
declare(strict_types=1);

namespace Src\Controllers;

class ErrorController extends BaseController
{
    public static function error403()
    {
        http_response_code(403);
//        echo $this->twig->render('Error/err.twig', [
//            'error_title' => '403 Forbidden',
//            'error_message' => 'You do not have permission to access this page.',
//        ]);
    }
}