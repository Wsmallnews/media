<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sn_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable()->comment('租户ID(NULL=无租户上下文)');
            $table->string('disk', 32)->comment('存储盘');
            $table->string('path')->comment('相对路径(唯一身份证,生成后不可变)');
            $table->string('name')->comment('原始文件名(展示/下载用,可改)');
            $table->string('ext', 16)->comment('扩展名(小写)');
            $table->string('mime', 128)->comment('MIME类型');
            $table->unsignedBigInteger('size')->default(0)->comment('大小(字节)');
            $table->unsignedInteger('width')->nullable()->comment('宽(仅图片)');
            $table->unsignedInteger('height')->nullable()->comment('高(仅图片)');
            $table->string('alt', 255)->nullable()->comment('替代文本');
            $table->string('uploader_type', 100)->nullable()->comment('上传者类型');
            $table->unsignedBigInteger('uploader_id')->nullable()->comment('上传者ID');
            $table->timestamps();

            $table->unique('path', 'sn_media_path_unique');
            $table->index('team_id');
            $table->index(['uploader_type', 'uploader_id'], 'sn_media_uploader_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sn_media');
    }
};
