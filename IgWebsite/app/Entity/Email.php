<?php
declare(strict_types=1);
namespace App\Entity;

class Email
{
    private ?string $id = null;
    private ?string $userId;
    private string $recipientEmail;
    private string $type;       // 'verified' | 'download'
    private string $openToken;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        ?string $userId = null,
        string $recipientEmail = '',
        string $type = 'verified',
        string $openToken = '',
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->userId = $userId;
        $this->recipientEmail = $recipientEmail;
        $this->type = $type;
        $this->openToken = $openToken;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    // Getter & Setter
    public function getId(): ?string
    {
        return $this->id;
    }
    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }
    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }
    public function setRecipientEmail(string $recipientEmail): void
    {
        $this->recipientEmail = $recipientEmail;
    }

    public function getType(): string
    {
        return $this->type;
    }
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getOpenToken(): string
    {
        return $this->openToken;
    }
    public function setOpenToken(string $openToken): void
    {
        $this->openToken = $openToken;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
?>