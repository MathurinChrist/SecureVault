<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['category:read', 'password:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['category:read', 'category:write', 'password:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['category:read', 'category:write', 'password:read'])]
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
