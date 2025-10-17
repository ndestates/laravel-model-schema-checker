<?php

namespace NDEstates\LaravelModelSchemaChecker\Models;

use NDEstates\LaravelModelSchemaChecker\Contracts\CodeImprovementInterface;

class CodeImprovement implements CodeImprovementInterface
{
    protected string $filePath;
    protected ?int $lineNumber;
    protected string $type;
    protected string $severity;
    protected string $title;
    protected string $description;
    protected array $suggestedChanges;
    protected bool $canAutoFix;
    protected string $originalCode;
    protected string $improvedCode;

    public function __construct(
        string $filePath,
        string $type,
        string $title,
        string $description,
        array $suggestedChanges = [],
        ?int $lineNumber = null,
        string $severity = 'medium',
        bool $canAutoFix = false,
        string $originalCode = '',
        string $improvedCode = ''
    ) {
        $this->filePath = $filePath;
        $this->type = $type;
        $this->title = $title;
        $this->description = $description;
        $this->suggestedChanges = $suggestedChanges;
        $this->lineNumber = $lineNumber;
        $this->severity = $severity;
        $this->canAutoFix = $canAutoFix;
        $this->originalCode = $originalCode;
        $this->improvedCode = $improvedCode;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSuggestedChanges(): array
    {
        return $this->suggestedChanges;
    }

    public function canAutoFix(): bool
    {
        return $this->canAutoFix;
    }

    public function applyFix(): bool
    {
        if (!$this->canAutoFix || !file_exists($this->filePath)) {
            return false;
        }

        try {
            $content = file_get_contents($this->filePath);
            $newContent = str_replace($this->originalCode, $this->improvedCode, $content);

            if ($newContent !== $content) {
                file_put_contents($this->filePath, $newContent);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getOriginalCode(): string
    {
        return $this->originalCode;
    }

    public function getImprovedCode(): string
    {
        return $this->improvedCode;
    }

    /**
     * Create a code improvement from search and replace
     */
    public static function fromSearchReplace(
        string $filePath,
        string $type,
        string $title,
        string $description,
        string $originalCode,
        string $improvedCode,
        ?int $lineNumber = null,
        string $severity = 'medium'
    ): self {
        $canAutoFix = !empty($originalCode) && !empty($improvedCode);

        return new self(
            $filePath,
            $type,
            $title,
            $description,
            [
                'search' => $originalCode,
                'replace' => $improvedCode,
            ],
            $lineNumber,
            $severity,
            $canAutoFix,
            $originalCode,
            $improvedCode
        );
    }
}