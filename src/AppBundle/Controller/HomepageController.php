<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use AppBundle\Entity\NewsCategory;
use AppBundle\Entity\News;
use AppBundle\Entity\ProductCat;
use AppBundle\Entity\Product;

class HomepageController extends Controller
{
    public function indexAction(Request $request)
    {
        $listCategoriesOnHomepage = $this->get('settings_manager')->get('listCategoryOnHomepage');
        $blocksOnHomepage = array();

        if (!empty($listCategoriesOnHomepage)) {
            $listCategoriesOnHomepage = json_decode($listCategoriesOnHomepage, true);

            if (is_array($listCategoriesOnHomepage)) {
                for ($i = 0; $i < count($listCategoriesOnHomepage); $i++) {
                    $blockOnHomepage = [];
                    $category = $this->getDoctrine()
                                    ->getRepository(NewsCategory::class)
                                    ->find($listCategoriesOnHomepage[$i]["id"]);

                    if ($category) {
                        $posts = $this->getDoctrine()
                                ->getRepository(News::class)
                                ->findBy(
                                    array('postType' => 'post', 'enable' => 1, 'category' => $category->getId()),
                                    array('createdAt' => 'DESC'),
                                    $listCategoriesOnHomepage[$i]["items"]
                                );
                    }

                    $blockOnHomepage = (object) array('category' => $category, 'posts' => $posts);
                    $blocksOnHomepage[] = $blockOnHomepage;
                }
            }
        }

        $productsNew = $this->getDoctrine()
            ->getRepository(Product::class)
            ->createQueryBuilder('n')
            ->where('n.enable = :enable')
            ->setParameter('enable', 1)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()->getResult();

        return $this->render('homepage/index.html.twig', [
            'blocksOnHomepage' => $blocksOnHomepage,
            'productsNew' => $productsNew,
            'showSlide' => true
        ]);
    }
}
