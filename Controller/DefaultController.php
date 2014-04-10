<?php

namespace Sopinet\TimelineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('SopinetTimelineBundle:Default:index.html.twig', array('name' => $name));
    }
}
