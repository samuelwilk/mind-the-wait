<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MercureTestController extends AbstractController
{
    #[Route('/test/mercure', name: 'app_test_mercure')]
    public function test(): Response
    {
        return $this->render('test_mercure.html.twig');
    }
}
