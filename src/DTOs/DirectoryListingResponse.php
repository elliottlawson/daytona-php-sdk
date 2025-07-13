<?php

namespace ElliottLawson\Daytona\DTOs;

class DirectoryListingResponse
{
    /**
     * @param FileInfo[] $files
     */
    public function __construct(
        public readonly array $files,
    ) {}

    public static function fromArray(array $data): self
    {
        // Handle both direct array of files and files nested under 'files' key
        $filesData = isset($data['files']) ? $data['files'] : $data;
        
        $files = array_map(
            fn(array $file) => FileInfo::fromArray($file),
            $filesData
        );

        return new self(files: $files);
    }

    public function toArray(): array
    {
        return array_map(
            fn(FileInfo $file) => $file->toArray(),
            $this->files
        );
    }
}