<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $color = null;

    #[ORM\ManyToMany(targetEntity: PasswordEntry::class, mappedBy: 'categories')]
    private Collection $passwordEntries;

    public function __construct()
    {
        $this->passwordEntries = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getColor(): ?string { return $this->color; }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getPasswordEntries(): Collection { return $this->passwordEntries; }
}
