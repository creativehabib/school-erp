<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customisable template per document type.
 *
 * The `body` column holds Blade markup edited by an administrator, which is why
 * `is_system` exists: system templates ship with the app and are rendered from disk
 * under resources/views/pdf/, while non-system rows are user-authored and compiled
 * from the database at render time through a restricted Blade sandbox.
 *
 * Never render a user-authored template with the full Blade compiler. Blade compiles
 * to PHP; an administrator who can save {{ }} can save arbitrary code. The renderer
 * in App\Services\Documents restricts these to a whitelisted placeholder set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 40)->comment('DocumentType enum');
            $table->string('paper_size', 20)->default('A4');
            $table->string('orientation', 12)->default('portrait');
            $table->unsignedSmallInteger('margin_top')->default(10);
            $table->unsignedSmallInteger('margin_right')->default(10);
            $table->unsignedSmallInteger('margin_bottom')->default(10);
            $table->unsignedSmallInteger('margin_left')->default(10);
            $table->unsignedTinyInteger('per_page')->default(1)->comment('Cards per sheet for grid layouts');
            $table->unsignedSmallInteger('card_width_mm')->nullable();
            $table->unsignedSmallInteger('card_height_mm')->nullable();
            $table->boolean('show_qr')->default(true);
            $table->boolean('show_barcode')->default(false);
            $table->boolean('show_photo')->default(true);
            $table->boolean('show_signature')->default(true);
            $table->string('background_path')->nullable()->comment('Pre-printed stationery scan');
            $table->longText('body')->nullable()->comment('User-authored markup; NULL = use the packaged view');
            $table->longText('styles')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['type', 'is_default'], 'document_templates_default_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
