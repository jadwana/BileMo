<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\User;
use App\Entity\Product;
use App\Entity\Customer;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $PasswordHasher;
    
    public function __construct(UserPasswordHasherInterface $PasswordHasher)
    {
        $this->PasswordHasher = $PasswordHasher;
    }
    
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Creation of products
        for ($i = 0 ; $i < 75 ; $i++) {
            
            $product = new Product();
            $product->setName(ucfirst($faker->word()))
                ->setPrice($faker->randomFloat(2, 8, 5000))
                ->setBrand($faker->company())
                ->setDescription($faker->text())
            ;
            $manager->persist($product);
        }

        // Creation of customer
        $customer = new Customer();
        $customer->setEmail("customer@mail.com");
        $customer->setRoles(["ROLE_USER"]);
        $customer->setPassword($this->PasswordHasher->hashPassword($customer, "password"));
        $customer->setname("Le Client");
        $manager->persist($customer);

        // Creation of users
        for ($i = 0; $i < 30; ++$i) {
            $user = new User();
            $user->setEmail($faker->email());
            $user->setUsername($faker->username());
            $hash = password_hash('123456', PASSWORD_BCRYPT);
            $user->setPassword($hash); 
            $user->setCustomer($customer);
            $manager->persist($user);
        }

        $manager->flush();
    }
    
}
