<?php
declare(strict_types=1);

namespace Src\Controllers;

use PDO;

class ErrorController extends BaseController
{
    /**
     * Method for displaying the 403 HTTP error.
     */
    public function forbiddenAction() // 403
    {
        http_response_code(403);
        echo $this->render('Errors/httpError.twig', [
            "header" => "Error 403 - Forbidden",
            "message" => "You do not have permission to access this page."
        ]);
    }

    /**
     * Method for displaying the 404 HTTP error.
     */
    public function notFoundAction() // 404
    {
        http_response_code(404);
        echo $this->render('Errors/httpError.twig', [
            "header" => "Error 404 - Not Found",
            "message" => "The page you are looking for does not exist."
        ]);
    }

    /**
     * Method for displaying the 405 HTTP error.
     */
    public function methodNotAllowedAction() // 405
    {
        http_response_code(405);
        echo $this->render('Errors/httpError.twig', [
            "header" => "Error 405 - Method Not Allowed",
            "message" => "The HTTP method used is not allowed for this resource."
        ]);
    }

    /**
     * Method for displaying the 500 HTTP error.
     */
    public function internalErrorAction() // 500
    {
        http_response_code(500);
        echo $this->render('Errors/httpError.twig', [
            "header" => "Error 500 - Internal Server Error",
            "message" => "An unexpected error occurred on the server."
        ]);
    }
}