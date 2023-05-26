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
     * @param  TagAwareCacheInterface $cache
     * 
     * @return JsonResponse
     */
    #[Route('/api/users', name: 'allUsers', methods:['GET'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste d\'utilisateurs')]
    public function getUserList(
        UserRepository $userRepository, 
        SerializerInterface $serializer, 
        Request $request, 
        TagAwareCacheInterface $cache
        ): JsonResponse
    {
       
        $page = $request->query->getInt('page', 1); 
        $limit = $request->query->getInt('limit', 3);
    
        $idCache = "getAllUsers-" . $page . "-" . $limit;
    
        $customer = $this->getUser();
        if ($customer) {
            $customerId = $customer->getId();
            
            $jsonUserList = $cache->get(
                $idCache, 
                function (ItemInterface $item) use ($userRepository, $page, $limit, $serializer, $customerId) {
                    // echo ("L'ELEMENT N'EST PAS ENCORE EN CACHE !\n");
                    $item->tag("usersCache");
                    $item->expiresAfter(60);
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

        // We retrieve the customer because must be logged in
        $customer = $this->getUser();
        // We get the customer's ID
        $customerId = $customer->getId();
        // We get the user
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
      *     description="Delete a user"
      *    
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
      * @param  EntityManagerInterface $manager
      * @param  TagAwareCacheInterface $cache
      * @return JsonResponse
      */
    #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste d\'utilisateurs')]
    public function deleteUser(User $user, EntityManagerInterface $manager, TagAwareCacheInterface $cache): JsonResponse
    {

        // We retrieve the customer because must be logged in
        $customer = $this->getUser();
        // We retrieve the customer of this user
        $userCustomer = $user->getCustomer();
       
        // We check that the customer logged in is that of the user
        if ($userCustomer == $customer) {
            $manager->remove($user);
            $manager->flush();
            // We empty the cache
            $cache->invalidateTags(["usersCache"]);
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
     * @param  EntityManagerInterface $manager
     * @param  UrlGeneratorInterface  $urlGenerator
     * @param  ValidatorInterface     $validator
     * @param  TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/users', name: 'addUser', methods: ['POST'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour ajouter un utilisateur')]
    public function addUser(
        Request $request, 
        SerializerInterface $serializer, 
        EntityManagerInterface $manager, 
        UrlGeneratorInterface $urlGenerator, 
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache
        ): JsonResponse
    {

        // We retrieve the customer because must be logged in
        $customer = $this->getUser();
        // We recover the data sent and we deserialize them
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        // We check for errors
        $errors = $validator->validate($user);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        // We pass the customer
        $user->setCustomer($customer);
        // We recover the password and we hash it
        $hash = password_hash($user->getPassword(), PASSWORD_BCRYPT);
        // We pass the hashed password
        $user->setPassword($hash);

        $manager->persist($user);
        $manager->flush();

        // We empty the cache. 
        $cache->invalidateTags(["usersCache"]);

        // We serialize the new user to display it
        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser= $serializer->serialize($user, 'json', $context);
        // We create the url to display this user
        $location = $urlGenerator->generate('detailUser', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['location' => $location], true);
       
    }


    /**
     * This method is used to update a user of a customer
     * 
     * @OA\Response(
     *     response=200,
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
     * @param  EntityManagerInterface $manager
     * @param  User                   $currentUser
     * @param  ValidatorInterface     $validator
     * @param  TagAwareCacheInterface $cache 
     * @return JsonResponse
     */
    #[Route('/api/users/{id}', name:"updateUser", methods:['PUT'])]
    public function updateUser(
        Request $request, 
        SerializerInterface $serializer, 
        User $currentUser, 
        EntityManagerInterface $manager, 
        ValidatorInterface $validator, 
        TagAwareCacheInterface $cache,
        UrlGeneratorInterface $urlGenerator
        ): JsonResponse 
    {
        // We retrieve the customer because must be logged in
        $customer = $this->getUser();
        // We retrieve the customer of this user
        $userCustomer = $currentUser->getCustomer();

        if ($userCustomer == $customer) {

            $newUser = $serializer->deserialize($request->getContent(), User::class, 'json');
            $currentUser->setUsername($newUser->getUsername());
            $currentUser->setEmail($newUser->getEmail());
            $hash = password_hash($newUser->getPassword(), PASSWORD_BCRYPT);
            // We pass the hashed password
            $currentUser->setPassword($hash);
    
            // We check for errors
            $errors = $validator->validate($currentUser);
            if ($errors->count() > 0) {
                return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
            }      
            
            $manager->persist($currentUser);
            $manager->flush();
    
            // We empty the cache. 
            $cache->invalidateTags(["usersCache"]);

            // We serialize the modified user to display it
            $context = SerializationContext::create()->setGroups(['getUsers']);
            $jsonUser= $serializer->serialize($currentUser, 'json', $context);
            // We create the url to display this user
            $location = $urlGenerator->generate('detailUser', ['id' => $currentUser->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            return new JsonResponse($jsonUser, Response::HTTP_OK, ['location' => $location], true);
    
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

}
