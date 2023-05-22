<?php

namespace App\Controller;

use App\Entity\User;
use Pagerfanta\Pagerfanta;
use OpenApi\Annotations as OA;
use App\Repository\UserRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Nelmio\ApiDocBundle\Annotation\Model;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class UserController extends AbstractController
{

   
    /**
     * This method is used to recover all users of a customer with pagination
     *
     * @OA\Response(
     *     response=200,
     *     description="Return the users list",
     *   @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class))
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
     *@OA\Tag(name="Users")
     * 
     * @param  UserRepository         $userRepository
     * @param  SerializerInterface    $serializer
     * @param  Request                $request
     * @param  TagAwareCacheInterface $cachePool
     * 
     * @return JsonResponse
     */
    #[Route('/api/users', name: 'allUsers', methods:['GET'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste d\'utilisateurs')]
    public function getProductList(
        UserRepository $userRepository, 
        SerializerInterface $serializer, 
        Request $request, 
        TagAwareCacheInterface $cachePool
        ): JsonResponse
    {
       
        $page = $request->query->getInt('page', 1); 
        $limit = $request->query->getInt('limit', 3);
    
        $idCache = "getAllUsers-" . $page . "-" . $limit;
    
        $customer = $this->getUser();
        if ($customer) {
            $customerId = $customer->getId();
            
            $jsonUserList = $cachePool->get(
                $idCache, 
                function (ItemInterface $item) use ($userRepository, $page, $limit, $serializer, $customerId) {
                    // echo ("L'ELEMENT N'EST PAS ENCORE EN CACHE !\n");
                    $item->tag("usersCache");
                    $query = $userRepository->findByCustomerId($customerId);
                    $userAdapter = new QueryAdapter($query);
                    $pagerfanta = new Pagerfanta($userAdapter);
                    $pagerfanta->setMaxPerPage($limit);
                    $pagerfanta->setCurrentPage($page);
    
                    $userList = $pagerfanta->getCurrentPageResults();

                    $context = SerializationContext::create()->setGroups(['getUsers']);
                    return $serializer->serialize($userList, 'json', $context);
                }
            );

            return new JsonResponse($jsonUserList, Response::HTTP_OK, ['Content-Type' => 'application/json'], true);
            
        }

        return new JsonResponse(null, Response::HTTP_NOT_FOUND);

    }

    /**
     * This method is used to recover the detail of a user of a customer
     * 
     * @OA\Response(
     *     response=200,
     *     description="Return the detail of a user",
     *     @Model(type=User::class)
     *     )
     * )
     * @OA\Response(
     *     response=404,
     *     description="Resource does not exist"
     * )
     * @OA\Response(
     *     response=401,
     *     description="Authenticated failed / invalid token"
     * )
     *  @OA\Response(
     *     response = 403,
     *     description = "Forbidden access to this content"
     * )
     *@OA\Tag(name="Users")
     * @param                   UserRepository      $userRepository
     * @param                   SerializerInterface $serializer
     * @param                   int                 $id
     * @return                  JsonResponse
     */
    #[Route('/api/users/{id}', name: 'detailUser', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste d\'utilisateurs')]
    public function getDetailUser(int $id, SerializerInterface $serializer, UserRepository $userRepository): JsonResponse
    {

        // On récupère le client car doit etre logué
        $customer = $this->getUser();
        // On récupère l'ID du client
        $customerId = $customer->getId();
        // On récupère l'utilisateur
        $user = $userRepository->findOneUser($customerId, $id);
       
        if ($user) {
            $context = SerializationContext::create()->setGroups(['getUsers']);
            $jsonUser = $serializer->serialize($user, 'json', $context);
            return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }


    /**
      * This method is used to delete the detail of a user of a customer
      * 
      * @OA\Response(
      *     response=204,
      *     description="Delete a user",
      *     @Model(type=User::class)
      *     )
      * )
      * @OA\Response(
      *     response=404,
      *     description="Resource does not exist"
      * )
      * @OA\Response(
      *     response=401,
      *     description="Authenticated failed / invalid token"
      * )
      *  @OA\Response(
      *     response = 403,
      *     description = "Forbidden access to this content"
      * )
      *@OA\Tag(name="Users")
      * @param  User                   $user
      * @param  EntityManagerInterface $em
      * @param  TagAwareCacheInterface $cachePool
      * @return JsonResponse
      */
    #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste d\'utilisateurs')]
    public function deleteUser(User $user, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {

        // On récupère le client car doit etre logué
        $customer = $this->getUser();
        // On récupère le client de cet utilisateur
        $userCustomer = $user->getCustomer();
       
        // On vérifie que le client logué est bien celui de l'utilisateur
        if ($userCustomer == $customer) {
            $em->remove($user);
            $em->flush();
            // On vide le cache
            $cachePool->invalidateTags(["userCache"]);
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }


    /**
     * This method is used to add a user of a customer
     *
     * @OA\Response(
      *     response=201,
      *     description="Add a new user",
      *     @Model(type=User::class)
      *     )
      * )
      * @OA\RequestBody(@Model(type=User::class, groups={"addUser"}))
      *
      * @OA\Response(
      *     response=401,
      *     description="Authenticated failed / invalid token"
      * )
      * 
      * @OA\Response(
      *     response = 403,
      *     description = "Forbidden access to this content"
      * )
      * 
     *@OA\Tag(name="Users")
     * @param  Request                $request
     * @param  SerializerInterface    $serializer
     * @param  EntityManagerInterface $em
     * @param  UrlGeneratorInterface  $urlGenerator
     * @param  ValidatorInterface     $validator
     * @return JsonResponse
     */
    #[Route('/api/users', name: 'addUser', methods: ['POST'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour ajouter un utilisateur')]
    public function addUser(
        Request $request, 
        SerializerInterface $serializer, 
        EntityManagerInterface $em, 
        UrlGeneratorInterface $urlGenerator, 
        ValidatorInterface $validator
        ): JsonResponse
    {

        // On récupère le client car doit etre logué
        $customer = $this->getUser();
        // On récupère les données envoyées et on les déserialise
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($user);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        // On passe le client
        $user->setCustomer($customer);
        // On recupère le mot de passe et on le hache
        $hash = password_hash($user->getPassword(), PASSWORD_BCRYPT);
        // On passe le mot de passe haché
        $user->setPassword($hash);

        $em->persist($user);
        $em->flush();

        // On sérialise le nv user pour l'afficher
        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser= $serializer->serialize($user, 'json', $context);
        // On crée l'url pour afficher cet utilisateur
        $location = $urlGenerator->generate('detailUser', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['location' => $location], true);
       
    }


    /**
     * This method is used to update a user of a customer
     * 
     * @OA\Response(
     *     response=204,
     *     description="The user has been updated"
     *     )
     * @OA\RequestBody(@Model(type=User::class, groups={"updateUser"}))
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
     * @OA\Tag(name="Users")
     * @param  Request                $request
     * @param  SerializerInterface    $serializer
     * @param  EntityManagerInterface $em
     * @param  User                   $currentUser
     * @param  ValidatorInterface     $validator
     * @param  TagAwareCacheInterface $cache 
     * @return JsonResponse
     */
    #[Route('/api/users/{id}', name:"updateUser", methods:['PUT'])]
    public function updateBook(
        Request $request, 
        SerializerInterface $serializer, 
        User $currentUser, 
        EntityManagerInterface $em, 
        ValidatorInterface $validator, 
        TagAwareCacheInterface $cache
        ): JsonResponse 
    {
        // On récupère le client car doit etre logué
        $customer = $this->getUser();
        // On récupère le client de cet utilisateur
        $userCustomer = $currentUser->getCustomer();

        if ($userCustomer == $customer) {

            $newUser = $serializer->deserialize($request->getContent(), User::class, 'json');
            $currentUser->setUsername($newUser->getUsername());
            $currentUser->setEmail($newUser->getEmail());
            $hash = password_hash($newUser->getPassword(), PASSWORD_BCRYPT);
            // On passe le mot de passe haché
            $currentUser->setPassword($hash);
    
            // On vérifie les erreurs
            $errors = $validator->validate($currentUser);
            if ($errors->count() > 0) {
                return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
            }      
            
            $em->persist($currentUser);
            $em->flush();
    
            // On vide le cache. 
            $cache->invalidateTags(["userCache"]);
    
            return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

}
