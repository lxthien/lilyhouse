<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use AppBundle\Entity\NewsCategory;
use AppBundle\Entity\News;
use AppBundle\Entity\Comment;
use AppBundle\Entity\Tag;
use AppBundle\Entity\Rating;
use AppBundle\Entity\Product;

use blackknight467\StarRatingBundle\Form\RatingType as RatingType;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;
use EWZ\Bundle\RecaptchaBundle\Form\Type\EWZRecaptchaType;
use EWZ\Bundle\RecaptchaBundle\Validator\Constraints\IsTrue as RecaptchaTrue;

class NewsController extends Controller
{
    /**
     * @var UploaderHelper
     */
    private $helper;

    /**
     * Constructs a new instance of UploaderExtension.
     *
     * @param UploaderHelper $helper
     */
    public function __construct(UploaderHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Render the list posts by the category
     * 
     * @return News
     */
    public function listAction($level1, $level2 = null, $page = 1)
    {
        $category = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->findOneBy(array('url' => $level1, 'enable' => 1));

        if (!$category) {
            throw $this->createNotFoundException("The item does not exist");
        }

        if (!empty($level2)) {
            $subCategory = $this->getDoctrine()
                ->getRepository(NewsCategory::class)
                ->findOneBy(array('url' => $level2, 'enable' => 1));

            if (!$subCategory) {
                throw $this->createNotFoundException("The item does not exist");
            }
        }

        // Init breadcrum for category page
        $breadcrumbs = $this->buildBreadcrums(!empty($level2) ? $subCategory : $category, null, null);

        if (empty($level2)) {
            // Get all post for this category and sub category
            $listCategoriesIds = array($category->getId());

            $allSubCategories = $this->getDoctrine()
                ->getRepository(NewsCategory::class)
                ->createQueryBuilder('c')
                ->where('c.parentcat = (:parentcat)')
                ->setParameter('parentcat', $category->getId())
                ->getQuery()->getResult();

            foreach ($allSubCategories as $value) {
                $listCategoriesIds[] = $value->getId();
            }

            $news = $this->getDoctrine()
                ->getRepository(News::class)
                ->createQueryBuilder('p')
                ->where('p.category IN (:listCategoriesIds)')
                ->andWhere('p.enable = :enable')
                ->setParameter('listCategoriesIds', $listCategoriesIds)
                ->setParameter('enable', 1)
                ->orderBy('p.createdAt', 'DESC')
                ->getQuery()->getResult();
        } else {
            $news = $this->getDoctrine()
                ->getRepository(News::class)
                ->createQueryBuilder('p')
                ->where('p.category = :category')
                ->andWhere('p.enable = :enable')
                ->setParameter('category', $subCategory->getId())
                ->setParameter('enable', 1)
                ->orderBy('p.createdAt', 'DESC')
                ->getQuery()->getResult();
        }

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $news,
            $page,
            $this->get('settings_manager')->get('numberRecordOnPage') ?: 10
        );

        return $this->render('news/list.html.twig', [
            'category' => !empty($level2) ? $subCategory : $category,
            'pagination' => $pagination
        ]);
    }

    /**
     * @Route("{slug}.html",
     *      defaults={"_format"="html"},
     *      name="news_show",
     *      requirements={
     *          "slug": "[^/\.]++"
     *      })
     */
    public function showAction($slug, Request $request)
    {
        if ($request->query->get('preview') === false || $request->query->get('preview_id') === null) {
            $post = $this->getDoctrine()
                ->getRepository(News::class)
                ->findOneBy(
                    array('url' => $slug, 'enable' => 1)
                );
        } else {
            $post = $this->getDoctrine()
                ->getRepository(News::class)
                ->find($request->query->get('preview_id'));
        }

        if (!$post) {
            throw $this->createNotFoundException("The item does not exist");
        }

        // Update viewCount for post
        $post->setViewCounts( $post->getViewCounts() + 1 );
        $this->getDoctrine()->getManager()->flush();

        // Get news related
        $relatedNews = $this->getDoctrine()
            ->getRepository(News::class)
            ->createQueryBuilder('r')
            ->where('r.id <> :id')
            ->andWhere('r.postType = :postType')
            ->andWhere('r.category = :category')
            ->andWhere('r.enable = :enable')
            ->setParameter('id', $post->getId())
            ->setParameter('postType', $post->getPostType())
            ->setParameter('category', $post->getCategory())
            ->setParameter('enable', 1)
            ->setMaxResults( 8 )
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Get the list comment for post
        $comments = $this->getDoctrine()
            ->getRepository(Comment::class)
            ->createQueryBuilder('c')
            ->where('c.news_id = :news_id')
            ->andWhere('c.approved = :approved')
            ->setParameter('news_id', $post->getId())
            ->setParameter('approved', 1)
            ->getQuery()->getResult();

        // Render form comment for post.
        $form = $this->renderFormComment($post);

        /* // Render form rating for post.
        $formRating = $this->createFormBuilder(null, array(
                'csrf_protection' => false,
            ))
            ->setAction($this->generateUrl('rating'))
            ->add('rating', RatingType::class)
            ->getForm();


        // Get rating of the post
        $repositoryRating = $this->getDoctrine()->getManager();

        $queryRating = $repositoryRating->createQuery(
            'SELECT AVG(r.rating) as ratingValue, COUNT(r) as ratingCount
            FROM AppBundle:Rating r
            WHERE r.news_id = :news_id'
        )->setParameter('news_id', $post->getId());

        $rating = $queryRating->setMaxResults(1)->getOneOrNullResult(); */

        // Init breadcrum for the post
        $breadcrumbs = $this->buildBreadcrums(null, $post, null);

        if ($post->isPage()) {
            return $this->render('news/page.html.twig', [
                'post'          => $post,
                'form'          => $form->createView(),
                'comments'      => $comments
            ]);
        } else {
            $imagePath = $this->helper->asset($post, 'imageFile');
            $imagePath = substr($imagePath, 1);
            $imageSize = getimagesize($imagePath);

            return $this->render('news/show.html.twig', [
                'post'          => $post,
                'form'          => $form->createView(),
                'relatedNews'   => $relatedNews,
                'comments'      => $comments,
                'imageSize'    => $imageSize
            ]);
        }
    }

    /**
     * @Route("/tags/{slug}.html",
     *      defaults={"_format"="html"},
     *      name="tags",
     *      requirements={
     *          "slug": "[^\n]+"
     *      }))
     */
    public function tagAction($slug, Request $request)
    {
        $tag = $this->getDoctrine()
            ->getRepository(Tag::class)
            ->findOneBy(
                array('url' => $slug)
            );

        // Get the list post related to tag
        $posts = $this->getDoctrine()
            ->getRepository(News::class)
            ->createQueryBuilder('n')
            ->innerJoin('n.tags', 't')
            ->where('t.id = :tags_id')
            ->andWhere('n.enable = :enable')
            ->setParameter('tags_id', $tag->getId())
            ->setParameter('enable', 1)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()->getResult();

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $posts,
            !empty($request->query->get('page')) ? $request->query->get('page') : 1,
            $this->get('settings_manager')->get('numberRecordOnPage') ?: 10
        );

        $breadcrumbs = $this->get("white_october_breadcrumbs");
        $breadcrumbs->addItem("home", $this->generateUrl("homepage"));
        $breadcrumbs->addItem('Tags > ' . $tag->getName());

        return $this->render('news/tags.html.twig', [
            'tag' => $tag,
            'pagination' => $pagination
        ]);
    }

    /**
     * Render list recent news
     * @return News
     */
    public function recentNewsAction()
    {
        $posts = $this->getDoctrine()
            ->getRepository(News::class)
            ->findBy(
                array('postType' => 'post', 'enable' => 1),
                array('createdAt' => 'DESC'),
                5
            );

        return $this->render('news/recent.html.twig', [
            'posts' => $posts,
        ]);
    }

    /**
     * Render list hot news
     * @return News
     */
    public function hotNewsAction()
    {
        $posts = $this->getDoctrine()
            ->getRepository(News::class)
            ->findBy(
                array('postType' => 'post', 'enable' => 1),
                array('viewCounts' => 'DESC'),
                15
            );

        return $this->render('news/hot.html.twig', [
            'posts' => $posts,
        ]);
    }

    /**
     * Render list news by category
     * @return News
     */
    public function listNewsByCategoryAction($categoryId)
    {
        $category = $this->getDoctrine()
            ->getRepository(NewsCategory::class)
            ->find($categoryId);
        
        $listCategoriesIds = array($category->getId());
        $allSubCategories = $this->getDoctrine()
                            ->getRepository(NewsCategory::class)
                            ->createQueryBuilder('c')
                            ->where('c.parentcat = (:parentcat)')
                            ->setParameter('parentcat', $category->getId())
                            ->getQuery()->getResult();

        foreach ($allSubCategories as $value) {
            $listCategoriesIds[] = $value->getId();
        }

        $posts = $this->getDoctrine()
            ->getRepository(News::class)
            ->createQueryBuilder('r')
            ->where('r.category IN (:listCategoriesIds)')
            ->andWhere('r.enable = :enable')
            ->setParameter('listCategoriesIds', $listCategoriesIds)
            ->setParameter('enable', 1)
            ->setMaxResults( 10 )
            ->orderBy('r.viewCounts', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('news/listByCategory.html.twig', [
            'posts' => $posts,
        ]);
    }

    /**
     * @Route("/rating", name="rating")
     * 
     * @return JSON
     */
    public function ratingAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $product = $this->getDoctrine()
            ->getRepository(Product::class)
            ->find($request->request->get('productId'));

        $rating = new Rating();
        $rating->setProduct($product);
        $rating->setRating($request->request->get('rating'));
        $rating->setName($request->request->get('name'));
        $rating->setEmail($request->request->get('email'));
        $rating->setTitle($request->request->get('title'));
        $rating->setContents($request->request->get('contents'));

        $em->persist($rating);

        $em->flush();
        
        return new Response(
            json_encode(
                array(
                    'status'=>'success',
                    'message' => 'C???m ??n ????nh gi?? c???a b???n'
                )
            )
        );
    }

    /**
     * @Route("/search", name="news_search")
     * 
     * @return News
     */
    public function handleSearchFormAction(Request $request)
    {
        $form = $this->createFormBuilder(null, array(
                'csrf_protection' => false,
            ))
            ->setAction($this->generateUrl('news_search'))
            ->setMethod('POST')
            ->add('q', TextType::class)
            ->add('search', ButtonType::class, array('label' => 'Search'))
            ->getForm();

        $form->handleRequest($request);
        
        if (!$form->isSubmitted() && empty($request->query->get('q'))) {
            return $this->render('news/formSearch.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        $q = $form->getData()['q'];
        if (!empty($q)) {
            return $this->redirectToRoute('news_search', array('q' => $q));
        }

        $query = $this->getDoctrine()
            ->getRepository(News::class)
            ->createQueryBuilder('p')
            ->where('p.title LIKE :q')
            ->andWhere('p.enable = :enable')
            ->andWhere('p.postType = :postType')
            ->setParameter('q', '%'.$request->query->get('q').'%')
            ->setParameter('enable', 1)
            ->setParameter('postType', 'post')
            ->getQuery();
        
        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query->getResult(),
            1,
            $this->get('settings_manager')->get('numberRecordOnPage') ?: 10
        );

        $breadcrumbs = $this->get("white_october_breadcrumbs");
        $breadcrumbs->addItem("home", $this->generateUrl("homepage"));
        $breadcrumbs->addItem('search');
        $breadcrumbs->addItem(ucfirst($request->query->get('q')));

        return $this->render('news/search.html.twig', [
            'q' => ucfirst($request->query->get('q')),
            'pagination' => $pagination
        ]);
    }

    /**
     * Render the form comment of news
     * 
     * @return Form
     **/
    private function renderFormComment($post)
    {
        $comment = new Comment();
        $comment->setIp( $this->container->get('request_stack')->getCurrentRequest()->getClientIp() );
        $comment->setNewsId( $post->getId() );

        $form = $this->createFormBuilder($comment)
            ->setAction($this->generateUrl('handle_comment_form'))
            ->add('content', TextareaType::class, array(
                'required' => true,
                'label' => 'label.content',
                'attr' => array('rows' => '7')
            ))
            ->add('author', TextType::class, array('label' => 'label.author'))
            ->add('email', EmailType::class, array('label' => 'label.author_email'))
            ->add('recaptcha', EWZRecaptchaType::class, array('label' => 'Capcha'))
            ->add('ip', HiddenType::class)
            ->add('news_id', HiddenType::class)
            ->add('comment_id', HiddenType::class)
            ->add('send', ButtonType::class, array('label' => 'label.send'))
            ->getForm();

        return $form;
    }

    /**
     * Handle form comment for post
     * 
     * @return JSON
     **/
    public function handleCommentFormAction(Request $request, \Swift_Mailer $mailer)
    {
        if (!$request->isXmlHttpRequest()) {
            return new Response(
                json_encode(
                    array(
                        'status'=>'error',
                        'message' => 'You can access this only using Ajax!'
                    )
                )
            );
        } else {
            $comment = new Comment();
            
            $form = $this->createFormBuilder($comment)
                ->add('content', TextareaType::class)
                ->add('author', TextType::class)
                ->add('email', EmailType::class)
                ->add('recaptcha', EWZRecaptchaType::class, array(
                    'mapped'      => false,
                    'constraints' => array(
                        new RecaptchaTrue()
                    )
                ))
                ->add('ip', HiddenType::class)
                ->add('news_id', HiddenType::class)
                ->add('comment_id', HiddenType::class)
                ->getForm();

            $form->handleRequest($request);

            if ($form->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($comment);
                $em->flush();

                if (null !== $comment->getId()) {
                    $message = \Swift_Message::newInstance()
                        ->setSubject($this->get('translator')->trans('comment.email.title', ['%siteName%' => $this->get('settings_manager')->get('siteName')]))
                        ->setFrom(['hotro.xaydungminhduy@gmail.com' => $this->get('settings_manager')->get('siteName')])
                        ->setTo($this->get('settings_manager')->get('emailContact'))
                        ->setBody(
                            $this->renderView(
                                'Emails/comment.html.twig',
                                array(
                                    'name' => $request->request->get('form')['author'],
                                    'body' => $request->request->get('form')['content']
                                )
                            ),
                            'text/html'
                        )
                    ;

                    $mailer->send($message);
    
                    return new Response(
                        json_encode(
                            array(
                                'status'=>'success',
                                'message' => '<div class="alert alert-success" role="alert">'.$this->get('translator')->trans('comment.thank_for_your_comment').'</div>'
                            )
                        )
                    );
                } else {
                    return new Response(
                        json_encode(
                            array(
                                'status'=>'error',
                                'message' => '<div class="alert alert-warning" role="alert">'.$this->get('translator')->trans('comment.have_a_problem_on_your_request').'</div>'
                            )
                        )
                    );
                }
            } else {
                return new Response(
                    json_encode(
                        array(
                            'status'=>'error',
                            'message' => '<div class="alert alert-warning" role="alert">'.$this->get('translator')->trans('comment.have_a_problem_on_your_request').'</div>'
                        )
                    )
                );
            }
        }
    }

    /**
     * Handle the breadcrumb
     * 
     * @return Breadcrums
     **/
    private function buildBreadcrums($category = null, $post = null, $page = null)
    {
        // Init october breadcrum
        $breadcrumbs = $this->get("white_october_breadcrumbs");
        
        // Add home item into first breadcrum.
        $breadcrumbs->addItem("home", $this->generateUrl("homepage"));
        
        // Breadcrum for category page
        if (!empty($category)) {
            if ($category->getParentcat() === 'root') {
                $breadcrumbs->addItem($category->getName(), $this->generateUrl("news_category", array('level1' => $category->getUrl() )));
            } else {
                $breadcrumbs->addItem($category->getParentcat()->getName(), $this->generateUrl("news_category", array('level1' => $category->getParentcat()->getUrl() )));
                $breadcrumbs->addItem($category->getName(), $this->generateUrl("list_category", array('level1' => $category->getParentcat()->getUrl(), 'level2' => $category->getUrl() )));
            }
        }

        // Breadcrum for post page
        if (!empty($post)) {
            $category = $post->getCategory();

            if (!empty($category)) {
                if ($category->getParentcat() === 'root') {
                    $breadcrumbs->addItem($category->getName(), $this->generateUrl("news_category", array('level1' => $category->getUrl() )));
                    $breadcrumbs->addItem($post->getTitle(), $this->generateUrl('news_show', array('slug' => $post->getUrl())) );
                } else {
                    $parentCategory = $category->getParentcat();
                    $breadcrumbs->addItem($parentCategory->getName(), $this->generateUrl("news_category", array('level1' => $parentCategory->getUrl() )));
                    $breadcrumbs->addItem($category->getName(), $this->generateUrl("list_category", array('level1' => $parentCategory->getUrl(), 'level2' => $category->getUrl() )));
                    $breadcrumbs->addItem($post->getTitle(), $this->generateUrl('news_show', array('slug' => $post->getUrl())) );
                }
            } else {
                $breadcrumbs->addItem($post->getTitle(), $this->generateUrl('news_show', array('slug' => $post->getUrl())) );
            }
        }

        return $breadcrumbs;
    }

    /**
     * @Route("/chi-phi-xay-dung", name="caculator_cost_construction")
     * 
     */
    public function caculatorCostConstructionAction($type = null, Request $request) {
        $form = $this->createFormBuilder(null, array(
                'csrf_protection' => false,
            ))
            ->setAction($this->generateUrl('caculator_cost_construction'))
            ->setMethod('POST')
            ->add('type', ChoiceType::class, array(
                'choices'  => array(
                    'Nh?? ph???' => 1,
                    'Bi???t th???' => 2,
                    'Nh?? c???p 4' => 3,
                ),
                'label' => 'Lo???i nh??'
            ))
            ->add('method', ChoiceType::class, array(
                'choices'  => array(
                    'X??y ph???n th??' => 1,
                    'X??y tr???n g??i' => 2,
                ),
                'label' => 'H??nh th???c x??y d???ng'
            ))
            ->add('wide', TextType::class, array(
                'label' => 'Chi???u r???ng (m)',
                'attr' => array(
                    'placeholder' => 'VD: Nh???p 4 ho???c 4.5'
                )
            ))
            ->add('long', TextType::class, array(
                'label' => 'Chi???u d??i (m)',
                'attr' => array(
                    'placeholder' => 'VD: Nh???p 12 ho???c 12.3'
                )
            ))
            ->add('floor', ChoiceType::class, array(
                'choices'  => array(
                    '1 tr???t' => 1,
                    '1 tr???t 1 l???u' => 2,
                    '1 tr???t 2 l???u' => 3,
                    '1 tr???t 3 l???u' => 4,
                    '1 tr???t 4 l???u' => 5,
                    '1 tr???t 5 l???u' => 6,
                    '1 tr???t 6 l???u' => 7,
                ),
                'label' => 'S??? t???ng'
            ))
            ->add('mong', ChoiceType::class, array(
                'choices'  => array(
                    'M??ng ????i c???c' => 1,
                    'M??ng b??ng' => 2,
                    'M??ng ????n' => 3,
                ),
                'label' => 'M??ng nh??'
            ))
            ->add('mai', ChoiceType::class, array(
                'choices'  => array(
                    'M??i b???ng ????c BTCT' => 1,
                    'M??i l???p t??n l???nh' => 2,
                    'M??i x?? g??? th??p l???p ng??i' => 3,
                    'M??i ????c BTCT l???p ng??i' => 4,
                ),
                'label' => 'M??i nh??'
            ))
            ->add('reset', ResetType::class, array(
                'label' => 'Nh???p l???i'
            ))
            ->add('caculator', SubmitType::class, array(
                'label' => 'D??? to??n chi ph??'
            ))
            ->getForm();

        $form->handleRequest($request);

        $costs = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $type = $form->get('type')->getData();
            $method = $form->get('method')->getData();
            $long = $form->get('long')->getData();
            $wide = $form->get('wide')->getData();
            $floor = $form->get('floor')->getData() ? $form->get('floor')->getData() : 1;
            $mong = $form->get('mong')->getData();
            $mai = $form->get('mai')->getData();
            $cost = 0;
            $title = '';
            $titleMong = '';
            $areaMong = 0;
            $titleMai = '';
            $areaMai = 0;
            $note = 'Chi ph?? x??y d???ng tr??n ch??? ??p d???ng ?????i v???i di???n t??ch x??y d???ng 80 m<sup>2</sup>/1s??n tr??? l??n. ??p d???ng v???i c??c nh?? ph??? th??ng d???ng kh??ng c?? c??c ki???n tr??c k???t c???u ?????c bi???t.';

            if (!is_numeric($long) || !is_numeric($wide) || !is_numeric($type) || !is_numeric($method) || !is_numeric($floor) || !is_numeric($mong) || !is_numeric($mai)) {
                $this->addFlash(
                    'error',
                    "Vui l??ng nh???p ????ng d??? li???u"
                );
                return $this->redirectToRoute('caculator_cost_construction');
            }

            $area = $long * $wide;

            if ($type === 1) {
                if ($method === 1) {
                    $cost = 2950000;
                    $title = "????n gi?? nh?? ph??? ph???n th??";
                } else {
                    $cost = 4600000;
                    $title = "????n gi?? nh?? ph??? tr???n g??i";
                }
            } elseif ($type === 3) {
                if ($method === 1) {
                    $cost = 2750000;
                    $title = "????n gi?? nh?? c???p 4 ph???n th??";
                } else {
                    $cost = 3900000;
                    $title = "????n gi?? nh?? c???p 4 tr???n g??i";
                }
            } else {
                if ($method === 1) {
                    $cost = 3200000;
                    $title = "????n gi?? bi???t th??? ph???n th??";
                } else {
                    $cost = 6000000;
                    $title = "????n gi?? bi???t th??? tr???n g??i";
                }
            }

            if ($type !== 3) {
                if ($mong === 1) {
                    $areaMong = $area * 0.5;
                } elseif ($mong === 2) {
                    $areaMong = $area * 0.55;
                } else {
                    $areaMong = $area * 0.3;
                }

                if ($mai === 1) {
                    $areaMai = $area * 0.4;
                } elseif ($mai === 2) {
                    $areaMai = $area * 0.25;
                } elseif ($mai === 3) {
                    $areaMai = $area * 0.7;
                } else {
                    $areaMai = $area * 1;
                }

                $areaTotal = ($area * $floor) + $areaMong + $areaMai;
            } else {
                $areaTotal = $area;
            }

            if ($mong === 1) {
                $titleMong = "M??ng ????i c???c";
            } elseif ($mong === 2) {
                $titleMong = "M??ng b??ng";
            } else {
                $titleMong = "M??ng ????n";
            }

            if ($mai === 1) {
                $titleMai = "M??i b???ng ????c BTCT";
            } elseif ($mai === 2) {
                $titleMai = "M??i l???p t??n l???nh";
            } elseif ($mai === 3) {
                $titleMai = "M??i x?? g??? th??p l???p ng??i";
            } else {
                $titleMai = "M??i ????c BTCT l???p ng??i";
            }
            
            $costs = (object) array(
                'area' => $area,
                'floor' => $floor,
                'titleMong' => $titleMong,
                'areaMong' => $areaMong,
                'titleMai' => $titleMai,
                'areaMai' => $areaMai,
                'areaTotal' => $areaTotal,
                'cost' => $cost,
                'costTotal' => $cost * $areaTotal,
                'title' => $title,
                'note' => $note
            );
        }

        $breadcrumbs = $this->get("white_october_breadcrumbs");
        $breadcrumbs->addItem("home", $this->generateUrl("homepage"));
        $breadcrumbs->addItem('D??? to??n chi ph?? x??y d???ng');

        $post = $this->getDoctrine()
            ->getRepository(News::class)
            ->findOneBy(
                array('url' => 'chi-phi-xay-dung')
            );

        if (!empty($type) && $type === 'page') {
            return $this->render('form/caculatorcost/page.html.twig', [
                'form' => $form->createView()
            ]);
        } elseif (!empty($type) && $type === 'sidebar') {
            return $this->render('form/caculatorcost/sidebar.html.twig', [
                'form' => $form->createView()
            ]);
        } else {
            return $this->render('form/caculatorcost/caculator.html.twig', [
                'form' => $form->createView(),
                'costs' => $costs ? $costs : null,
                'post' => $post
            ]);
        }
    }
}