<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\CustomerRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UserController extends AbstractController
{
    #[Route('/api/users', name: 'allUsers', methods:['GET'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste d\'utilisateurs')]
    public function getProductList(UserRepository $userRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $offset = $request->get('offset', 1);
        $limit = $request->get('limit', 3);

        // $idCache = "getAllUsers-" . $offset . "-" . $limit;
        
        $customer = $this->getUser();
       
        $customerId = $customer->getId();
       
        if($customer){
            
            // $userList = $cachePool->get($idCache, function(ItemInterface $item) use ($customerId, $offset, $limit, $userRepository){
            //     echo ("L'ELEMENT N'EST PAS ENCORE EN CACHE !\n");
            //     $item->tag("userCache");
            //     return $userRepository->findByCustomerIdWithPagination($customerId, $offset, $limit);
            // });
            $userList =  $userRepository->findByCustomerIdWithPagination($customerId, $offset, $limit);
    
            $jsonUserList = $serializer->serialize($userList, 'json', ['groups' => 'getUsers']);
            return new JsonResponse($jsonUserList, Response::HTTP_OK, ['accept' => 'json'], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }

   #[Route('/api/users/{id}', name: 'detailUser', methods: ['GET'])]
    public function getDetailUser(int $id, SerializerInterface $serializer, UserRepository $userRepository): JsonResponse {


        $customer = $this->getUser();
       
        $customerId = $customer->getId();

        $user = $userRepository->findOneUser($customerId, $id);
       
        
        if ($user) {
            $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUsers']);
            return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
   }

   

}
