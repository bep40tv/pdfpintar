<?php

namespace App\Jobs;

use App\Http\Repository\DocumentRepository;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Embedding;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mis3085\Tiktoken\Facades\Tiktoken;
use App\Service\PdfinfoService;
use Spatie\PdfToText\Pdf;

class ProcessEmbeddingDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 2 * 60 * 60; // 2 hours

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Document $document,
    ) {
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['embedding', 'document:' . $this->document->id];
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentRepository $documentRepository): void
    {
        try {
            $collection = Collection::create([
                'name' => $this->document->path,
                'cmetadata' => json_encode([
                    "total_token" => 0,
                    "title" => $this->document->title
                ])
            ]);

            $pdf_path =  storage_path("app/" . $this->document->path);
            $total_token_embed = 0;

            (new PdfinfoService(env('BIN_PDF_INFO')))
                ->tapPage($pdf_path, function ($page) use ($total_token_embed, $documentRepository, $collection, $pdf_path) {
                    $text = (new Pdf(env('BIN_PDF_TO_TEXT')))
                        ->setPdf($pdf_path)
                        ->addOptions(['f ' . $page, 'l ' . $page])
                        ->text();
                    $total_token = Tiktoken::count($text);
                    $total_token_embed += $total_token;
                    $vectors = $documentRepository->getQueryEmbedding($text);
                    Embedding::create([
                        "collection_id" => $collection->uuid,
                        "embedding" => json_encode($vectors),
                        "document" => $text,
                        "cmetadata" => json_encode([
                            "total_token" => $total_token,
                            "page" => $page,
                            "path" => $this->document->path,
                            "title" => $this->document->title
                        ])
                    ]);
                    $this->document->update([
                        'status' => "Embedding page {$page}"
                    ]);
                });


            $collection->update([
                'cmetadata' => json_encode([
                    "total_token" => $total_token_embed,
                    "title" => $this->document->title
                ])
            ]);

            $this->document->update([
                'status' => "complete"
            ]);
            echo "total_token_embed: $total_token_embed\n";
        } catch (Exception $e) {
            Log::info($e->getMessage());
        }
    }
}
