<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskAttachmentRequest;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    /**
     * Upload one or more task attachments.
     */
    public function store(StoreTaskAttachmentRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $uploaded = [];

        foreach ($request->file('files', []) as $file) {
            $path = $file->store('task-attachments/'.$task->id, 'public');

            $attachment = $task->attachments()->create([
                'uploaded_by' => $request->user()->id,
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize() ?: 0,
                'mime_type' => $file->getMimeType(),
            ]);

            $uploaded[] = $attachment->filename;
        }

        ActivityLogger::log(
            $request->user(),
            $task->workspace,
            'task.attachment_uploaded',
            'Uploaded '.count($uploaded).' attachment(s) to '.$task->title,
            $task,
            ['task_id' => $task->id, 'files' => $uploaded],
        );

        return back()->with('status', 'Attachments uploaded.');
    }

    /**
     * Delete an attachment.
     */
    public function destroy(TaskAttachment $attachment): RedirectResponse
    {
        $task = $attachment->task;

        $this->authorize('update', $task);

        Storage::disk('public')->delete($attachment->path);

        $filename = $attachment->filename;
        $attachmentId = $attachment->id;

        $attachment->delete();

        ActivityLogger::log(
            request()->user(),
            $task->workspace,
            'task.attachment_deleted',
            'Deleted attachment '.$filename,
            $task,
            ['task_id' => $task->id, 'attachment_id' => $attachmentId],
        );

        return back()->with('status', 'Attachment deleted.');
    }
}
