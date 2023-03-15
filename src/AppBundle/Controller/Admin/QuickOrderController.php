<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\QuickOrder;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/admin/quickorder")
 * @Security("has_role('ROLE_ADMIN')")
 */

class QuickOrderController extends Controller
{
    /**
     * @Route("/", name="admin_quickorder_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $objects = $this->getDoctrine()
                ->getRepository(QuickOrder::class)
                ->findBy(
                    array(),
                    array('createdAt' => 'DESC')
                );

        return $this->render('admin/quickorder/index.html.twig', ['objects' => $objects]);
    }
}