<?php

namespace App\Controller\Core\Api;


use Symfony\Component\HttpFoundation\JsonResponse;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\Post;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Component\HttpFoundation\Request;


class SeoController extends FOSRestController
{
use ControllerTrait;

/**
* @var \Twig_Environment
*/
private $environment;

/**
* //     *  ApiDoc_(
* //     *  resource=true,
* //     *  Headers = seoparam,
* //     *  description="Проверка SEO шаблона",
* //     *  section="",
* //     *  authentication=true,
* //     *  parameters={"params": {"a": 2, "b": 2}, "template": "asa{{a}}sas"}
* //  * )
*
* @Post(path="/seo/params")
* @param Request $request
* @return \Symfony\Component\HttpFoundation\JsonResponse
*/
public function renderSeoTemplateAction(Request $request)
{

$data = json_decode($request->getContent(), true);

/** @var \Twig_Environment $twig */
$twig = $this->container->get('twig');

$result = $twig->createTemplate($data['template'])->render($data['params']);

return new JsonResponse([
'result' => $result
]);
}
}