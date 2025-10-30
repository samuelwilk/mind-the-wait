<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for static pages (privacy policy, about, etc.).
 */
final class PageController extends AbstractController
{
    #[Route('/privacy', name: 'app_privacy')]
    public function privacy(): Response
    {
        return $this->render('page/privacy.html.twig');
    }
}
