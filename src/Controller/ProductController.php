<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
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
        $offset = $request->get('offset', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllProducts-" . $offset . "-" . $limit;

        $productList = $cachePool->get($idCache, function(ItemInterface $item) use ($productRepository, $offset, $limit){
            // echo ("L'ELEMENT N'EST PAS ENCORE EN CACHE !\n");
            $item->tag("productCache");
            return $productRepository->findAllWithPagination($offset, $limit);
        });

        $jsonProductList = $serializer->serialize($productList, 'json');
        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/products/{id}', name: 'detailProduct', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à ce produit')]
    public function getDetailProduct(Product $product, SerializerInterface $serializer): JsonResponse 
    {
        
        $jsonProduct = $serializer->serialize($product, 'json');
        return new JsonResponse($jsonProduct, Response::HTTP_OK, ['accept' => 'json'], true);
    }

}
