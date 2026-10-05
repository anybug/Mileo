<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NewsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;
use Symfony\Component\HttpFoundation\File\File;

#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: NewsRepository::class)]
class News
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $poster = null;

    #[Vich\UploadableField(mapping: 'news_poster', fileNameProperty: 'poster')]
    private ?File $posterFile = null;

    #[Assert\NotNull(message: 'La date de parution est obligatoire.')]
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'isPublished', type: Types::BOOLEAN)]
    private ?bool $isPublished = false;

    public function __construct()
    {
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->title ?? 'Actualité';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function setPoster(?string $poster): static
    {
        $this->poster = $poster;

        return $this;
    }

    public function getPosterFile(): ?File
    {
        return $this->posterFile;
    }

    public function setPosterFile(?File $posterFile = null): void
    {
        $this->posterFile = $posterFile;

        if (null !== $posterFile) {
            $this->setUpdatedAt(new \DateTime());        
        }
    }

    public function isIsPublished(): ?bool
    {
        return $this->isPublished;
    }

}