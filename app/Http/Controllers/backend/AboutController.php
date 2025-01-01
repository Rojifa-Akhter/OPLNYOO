<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\About;
use Illuminate\Http\Request;

class AboutController extends Controller
{
    public function about(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            // 'images' => 'nullable|array|max:3',
        ]);

        $user = auth()->user(); // Assuming you're using authentication
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $about = About::first();

        // $newImages = [];
        // if ($request->hasFile('images')) {
        //     foreach ($request->file('images') as $image) {
        //         $path = $image->store('about_images', 'public');
        //         $newImages[] = asset('storage/' . $path);
        //     }
        // }

        if ($about) {
            // Get existing images (if any)
            // $existingImages = json_decode($about->image, true) ?: [];

            // // Merge new images with existing images, but ensure there are no more than 3 images
            // $allImages = array_merge($existingImages, $newImages);
            // if (count($allImages) > 3) {
            //     $allImages = array_slice($allImages, 0, 3);
            // }

            $about->update([
                'title' => $request->title,
                'description' => $request->description,
                // 'image' => json_encode($allImages),
                'owner_id' => $user->id, // Update the owner_id
            ]);
        } else {
            $about = About::create([
                'title' => $request->title,
                'description' => $request->description,
                // 'image' => json_encode($newImages),
                'owner_id' => $user->id, // Set the owner_id
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => $about->wasRecentlyCreated ? 'About created successfully' : 'About updated successfully',
            'about' => $about,
        ], 200);
    }
    public function aboutView(Request $request)
    {
        
        $about = About::all();

        return response()->json(['status' => 'success', 'message'=>$about]);
    }
}
