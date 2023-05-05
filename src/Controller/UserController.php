<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManager;
use App\Repository\UserRepository;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

        $idCache = "getAllUsers-" . $offset . "-" . $limit;
        
        $customer = $this->getUser();
       
        $customerId = $customer->getId();
       
        if($customer){
            
            $userList = $cachePool->get($idCache, function(ItemInterface $item) use ($customerId, $offset, $limit, $userRepository){
                echo ("L'ELEMENT N'EST PAS ENCORE EN CACHE !\n");
                $item->tag("userCache");
                // $item->expiresAfter(60);
                return $userRepository->findByCustomerIdWithPagination($customerId, $offset, $limit);
            });
    
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

   #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
    public function deleteUser(User $user, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse {

        // On récupère le client car doit etre logué
        $customer = $this->getUser();
        // On récupère le client de cet utilisateur
        $userCustomer = $user->getCustomer();
       
        // on vérifie que le client logué est bien celui de l'utilisateur
        if ($userCustomer == $customer) {
           $em->remove($user);
           $em->flush();
           // On vide le cache
           $cachePool->invalidateTags(["userCache"]);
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
   }

   #[Route('/api/users', name: 'addUser', methods: ['POST'])]
   public function addUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): JsonResponse {

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
        $jsonUser= $serializer->serialize($user, 'json', ['groups' => 'getUsers']);
        // On crée l'url pour afficher cet utilisateur
        $location = $urlGenerator->generate('detailUser', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['location' => $location], true);
       
       
  }


}
