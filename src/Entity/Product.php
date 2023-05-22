<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ProductRepository;
use JMS\Serializer\Annotation\Groups;
use Hateoas\Configuration\Annotation as Hateoas;

/**
 * @Hateoas\Relation(
 *      "self",
 *      href = @Hateoas\Route(
 *          "detailProduct",
 *          parameters = { "id" = "expr(object.getId())" }
 *      ),
 *     exclusion = @Hateoas\Exclusion(excludeIf = "expr(not is_granted('ROLE_CLIENT'))"), 
 * )
 *
 * @Hateoas\Relation(
 *      "allProducts",
 *      href = @Hateoas\Route(
 *          "app_product"
 *      ),
 *      exclusion = @Hateoas\Exclusion(excludeIf = "expr(not is_granted('ROLE_CLIENT'))"),
 * )
 * @Hateoas\Relation(
 *      "updateProduct",
 *      href = @Hateoas\Route(
 *          "updateProduct",
 *          parameters = { "id" = "expr(object.getId())" }
 *      ),
 *      exclusion = @Hateoas\Exclusion(excludeIf = "expr(not is_granted('ROLE_ADMIN'))"),
 * )
 */

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(["updateProduct"])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(["updateProduct"])]
    private ?float $price = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(["updateProduct"])]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Groups(["updateProduct"])]
    private ?string $brand = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): self
    {
        $this->brand = $brand;

        return $this;
    }
}
