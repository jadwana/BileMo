<?php

namespace App\Controller;

use App\Entity\Product;
use Pagerfanta\Pagerfanta;
use App\Repository\ProductRepository;
use JMS\Serializer\SerializerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProductController extends AbstractController
{
    /**
     * This method is used to recover all the products with pagination
     *
     * @param ProductRepository $productRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/products', name: 'app_product', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste de produits')]
    public function getProductList(ProductRepository $productRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $limit = $request->get('limit', 3);
        $offset = $request->query->getInt('page', 1);
        $idCache = "getAllProducts-" . $offset . "-" . $limit;

        $cachedProductList = $cachePool->getItem($idCache);
        if (!$cachedProductList->isHit()) {
            $productAdapter = new QueryAdapter($productRepository->createQueryBuilder('p'));
            $pagerfanta = new Pagerfanta($productAdapter);
            $pagerfanta->setMaxPerPage($limit);
            $pagerfanta->setCurrentPage($offset);

            $productList = $pagerfanta->getCurrentPageResults();

            $cachedProductList->set($productList);
            $cachedProductList->tag("productCache");
            $cachedProductList->expiresAfter(60);

            $cachePool->save($cachedProductList);
        } else {
            $productList = $cachedProductList->get();
        }


        $jsonProductList = $serializer->serialize($productList, 'json');
        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
    }
    
    /**
     * This method is used to recover the detail of a product
     *
     * @param Product $product
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name: 'detailProduct', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à ce produit')]
    public function getDetailProduct(Product $product, SerializerInterface $serializer): JsonResponse 
    {
        
        $jsonProduct = $serializer->serialize($product, 'json');
        return new JsonResponse($jsonProduct, Response::HTTP_OK, ['accept' => 'json'], true);
    }

}
