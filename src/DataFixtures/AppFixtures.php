<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Product;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 0 ; $i < 75 ; $i++) {
            
            $product = new Product();
            $product->setName(ucfirst($faker->word()))
                ->setPrice($faker->randomFloat(2, 8, 5000))
                ->setBrand($faker->company())
                ->setDescription($faker->text())
            ;
            $manager->persist($product);
        }

        $manager->flush();
    }
}
