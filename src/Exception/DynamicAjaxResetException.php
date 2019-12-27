<?php


namespace App\Exception;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DynamicAjaxResetException extends HttpException
{
    public function __construct(Request $request)
    {
        if ($request->isXmlHttpRequest())
            parent::__construct(403, '', null, [
                'X-AJAX-Control' => 'reset'
            ], 0);
        else
            parent::__construct(403, 'Request terminated.', null, [], 0);
    }
}