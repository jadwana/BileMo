<?php

namespace App\Controller;

use App\Entity\User;
use Pagerfanta\Pagerfanta;
use App\Repository\UserRepository;
use JMS\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Symfony\Contracts\Cache\ItemInterface;
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
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/users', name: 'allUsers', methods:['GET'])]
    #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste d\'utilisateurs')]
    public function getProductList(UserRepository $userRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cachePool): JsonResponse
    {
       
        $offset = $request->query->getInt('page', 1); 
        $limit = $request->query->getInt('limit', 3);
    
        $idCache = "getAllUsers-" . $offset . "-" . $limit;
    
        $customer = $this->getUser();
        if ($customer) {
            $customerId = $customer->getId();
    
            $cachedUserList = $cachePool->getItem($idCache);
            if (!$cachedUserList->isHit()) {
                $query = $userRepository->findByCustomerId($customerId);
                $userAdapter = new QueryAdapter($query);
                // $userAdapter->where('u.customer = :customerId')->setParameter('customerId', $customerId);
                $pagerfanta = new Pagerfanta($userAdapter);
                $pagerfanta->setMaxPerPage($limit);
                $pagerfanta->setCurrentPage($offset);
    
                $userList = $pagerfanta->getCurrentPageResults();
    
                $cachedUserList->set($userList);
                $cachedUserList->tag("userCache");
                $cachedUserList->expiresAfter(60);
    
                $cachePool->save($cachedUserList);
            } else {
                $userList = $cachedUserList->get();
            }
            $context = SerializationContext::create()->setGroups(['getUsers']);
            $jsonUserList = $serializer->serialize($userList, 'json', $context);

        return new JsonResponse($jsonUserList, Response::HTTP_OK, ['Content-Type' => 'application/json'], true);
    }

    return new JsonResponse(null, Response::HTTP_NOT_FOUND);

    }

    /**
     * This method is used to recover the detail of a user of a customer
     *
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param int $id
     * @return JsonResponse
     */
   #[Route('/api/users/{id}', name: 'detailUser', methods: ['GET'])]
   #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste d\'utilisateurs')]
    public function getDetailUser(int $id, SerializerInterface $serializer, UserRepository $userRepository): JsonResponse {

        // On récupère le client car doit etre logué
        $customer = $this->getUser();
        // on récupère l'ID du client
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
     * @param User $user
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
   #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
   #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste d\'utilisateurs')]
    public function deleteUser(User $user, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse {

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
    * @param Request $request
    * @param SerializerInterface $serializer
    * @param EntityManagerInterface $em
    * @param UrlGeneratorInterface $urlGenerator
    * @param ValidatorInterface $validator
    * @return JsonResponse
    */
   #[Route('/api/users', name: 'addUser', methods: ['POST'])]
   #[IsGranted('ROLE_CLIENT', message: 'Vous n\'avez pas les droits suffisants pour accéder à cette liste d\'utilisateurs')]
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
        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser= $serializer->serialize($user, 'json', $context);
        // On crée l'url pour afficher cet utilisateur
        $location = $urlGenerator->generate('detailUser', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ['location' => $location], true);
       
       
  }


}
