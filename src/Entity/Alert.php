<?php

namespace App\Entity;

use App\Repository\AlertRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
class Alert extends BaseNotification
{
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $category = 'general';

    public function __construct()
    {
        parent::__construct();
        $this->type = 'info';
    }

    public function getDescription(): ?string { return $this->description; }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCategory(): ?string { return $this->category; }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }
}
