<?php

namespace CryptX;

final class EmailProcessingConfig {
    public function __construct(
        private readonly string $content,
        private readonly bool $isShortcode = false,
        private readonly ?int $postId = null
    ) {}

    public function getContent(): string
    {
        return $this->content;
    }

    public function isShortcode(): bool
    {
        return $this->isShortcode;
    }

    public function getPostId(): ?int
    {
        return $this->postId;
    }
}
