<?php

namespace Wsmallnews\Media\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Wsmallnews\Media\Exceptions\MediaException;
use Wsmallnews\Media\Support\MediaManager;

/**
 * FilePicker（不进素材库的上传形态）的直传端点：
 * multipart 上传 → MediaManager 统一管道（校验/写盘/宽高/入账）→ 返回值对象 + 预览 URL。
 * 注意：入账与素材库一致（审计无黑户文件），只是字段引用不经弹窗选择。
 */
class MediaUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']]);

        $disk = $request->input('disk');

        try {
            $media = app(MediaManager::class)->upload(
                $request->file('file'),
                disk: is_string($disk) && $disk !== '' ? $disk : null,
            );
        } catch (MediaException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'value' => $media->toValue(),
            'url' => $media->url(200),
        ]);
    }
}
