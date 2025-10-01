<?php
declare(strict_types=1);
namespace App\Entity;

class EmailEvent
{
    private ?string $id;
    private string $emailId;
    private string $event;       // 'open' | 'click' | 'download'
    private \DateTimeImmutable $at;

    public function __construct(
        ?string $id = null,
        string $emailId = '',
        string $event = 'open',
        ?\DateTimeImmutable $at = null
    ) {
        $this->id = $id;
        $this->emailId = $emailId;
        $this->event = $event;
        $this->at = $at ?? new \DateTimeImmutable();
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

    public function getEmailId(): string
    {
        return $this->emailId;
    }
    public function setEmailId(string $emailId): void
    {
        $this->emailId = $emailId;
    }

    public function getEvent(): string
    {
        return $this->event;
    }
    public function setEvent(string $event): void
    {
        $this->event = $event;
    }

    public function getAt(): \DateTimeImmutable
    {
        return $this->at;
    }
    public function setAt(\DateTimeImmutable $at): void
    {
        $this->at = $at;
    }
}
?>