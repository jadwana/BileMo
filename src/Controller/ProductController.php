<?php

namespace App\Controller;

use App\Entity\Product;
use Pagerfanta\Pagerfanta;
use OpenApi\Annotations as OA;
use App\Repository\ProductRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProductController extends AbstractController
{
    /**
     * This method is used to recover all the products with pagination
     * 
     * @OA\Response(
     *     response=200,
     *     description="Return the products list",
     *   @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Product::class))
     *     )
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="The page we want to retrieve",
     *     @OA\Schema(type="int")
     * )
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="The number of elements to be retrieved",
     *     @OA\Schema(type="int")
     * )
     * @OA\Response(
     *     response=404,
     *     description="Resource does not exist"
     * )
     * 
     * @OA\Response(
     *     response=401,
     *     description="Authenticated failed / invalid token"
     * )
     * @OA\Response(
     *     response = 403,
     *     description = "Forbidden access to this content"
     * )
     * @OA\Tag(name="Products")
     *
     * @param  ProductRepository      $productRepository
     * @param  SerializerInterface    $serializer
     * @param  Request                $request
     * @param  TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/products', name: 'app_product', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste de produits')]
    public function getProductList(
        ProductRepository $productRepository, 
        SerializerInterface $serializer, 
        Request $request, 
        TagAwareCacheInterface $cachePool
    ): JsonResponse {
        $limit = $request->get('limit', 3);
        $page = $request->query->getInt('page', 1);
        $idCache = "getAllProducts-" . $page . "-" . $limit;
        
        $jsonProductList = $cachePool->get(
            $idCache, 
            function (ItemInterface $item) use ($productRepository, $page, $limit, $serializer) {
                // echo ("L'ELEMENT N'EST PAS ENCORE EN CACHE !\n");
                $item->tag("ProductsCache");
                $productAdapter = new QueryAdapter($productRepository->createQueryBuilder('p'));
                $pagerfanta = new Pagerfanta($productAdapter);
                $pagerfanta->setMaxPerPage($limit);
                $pagerfanta->setCurrentPage($page);

                $productList = $pagerfanta->getCurrentPageResults();
        
                return $serializer->serialize($productList, 'json');
            }
        );



        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
    }
    
    /**
     * This method is used to recover the detail of a product
     * 
     * @OA\Response(
     *     response=200,
     *     description="Return the detail of a product",
     *     @Model(type=Product::class)
     *     )
     * 
     * @OA\Response(
     *     response=404,
     *     description="Resource does not exist"
     * )
     * @OA\Response(
     *     response=401,
     *     description="Authenticated failed / invalid token"
     * )
     * @OA\Response(
     *     response = 403,
     *     description = "Forbidden access to this content"
     * )
     * @OA\Tag(name="Products")
     * 
     * @param  Product             $product
     * @param  SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name: 'detailProduct', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à ce produit')]
    public function getDetailProduct(Product $product, SerializerInterface $serializer): JsonResponse 
    {
        
        $jsonProduct = $serializer->serialize($product, 'json');
        return new JsonResponse($jsonProduct, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * This method is used to update a product
     * 
     * @OA\Response(
     *     response=204,
     *     description="The product has been updated"
     *     )
     * 
     * @OA\RequestBody(@Model(type=Product::class, groups={"updateProduct"}))
     * 
     * @OA\Response(
     *     response=404,
     *     description="Resource does not exist"
     * )
     * @OA\Response(
     *     response=401,
     *     description="Authenticated failed / invalid token"
     * )
     * @OA\Response(
     *     response = 403,
     *     description = "Forbidden access to this content"
     * )
     * @OA\Tag(name="Products")
     * 
     * @param  Product                $currentProduct
     * @param  SerializerInterface    $serializer
     * @param  Request                $request
     * @param  EntitymanagerInterface $em
     * @param  ValidatorInterface     $validator
     * @param  TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/products/{id}', name:"updateProduct", methods:['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un produit')]
    public function updateBook(
        Request $request, 
        SerializerInterface $serializer, 
        Product $currentProduct, 
        EntityManagerInterface $em, 
        ValidatorInterface $validator, 
        TagAwareCacheInterface $cache
    ): JsonResponse {

        $newProduct = $serializer->deserialize(
            $request->getContent(), Product::class, 'json'
        );
        $currentProduct->setName($newProduct->getName());
        $currentProduct->setPrice($newProduct->getPrice());
        $currentProduct->setDescription($newProduct->getDescription());
        $currentProduct->setBrand($newProduct->getBrand());

        // On vérifie les erreurs
        $errors = $validator->validate($currentProduct);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }      
        
        $em->persist($currentProduct);
        $em->flush();

        // On vide le cache. 
        $cache->invalidateTags(["productsCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

}
