<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;

class HelpArticleController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'size' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|min:3'
        ]);

        try {
            $query = HelpArticle::query();

            if ($request->has('search')) {
                $query->where(function ($query) use ($request) {
                    $query->where('title', 'like', '%' . $request->search . '%')
                        ->orWhere('content', 'like', '%' . $request->search . '%');
                });
            }

            $page = $request->get('page', 1);
            $size = $request->get('size', 10);
            $articles = $query->paginate($size, ['*'], 'page', $page);

            return response()->json([
                'status_code' => 200,
                'success' => true,
                'data' => [
                    'articles' => $articles->items(),
                    'pagination' => [
                        'page' => $articles->currentPage(),
                        'size' => $articles->perPage(),
                        'total_pages' => $articles->lastPage(),
                        'total_items' => $articles->total()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'success' => false,
                'message' => 'Failed to retrieve help articles. Please try again later.',
                'error' => $e->getMessage() // Include error message
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'status_code' => 401,
                'success' => false,
                'message' => 'Authentication failed'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|uuid|exists:users,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 422,
                'success' => false,
                'message' => 'Invalid input data.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $article = HelpArticle::create([
                'user_id' => $request->user_id,
                'title' => $request->title,
                'content' => $request->content,
            ]);

            return response()->json([
                'status_code' => 201,
                'success' => true,
                'message' => 'Help article created successfully.',
                'data' => $article
            ], 201);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') { // Unique violation error code
                return response()->json([
                    'status_code' => 409,
                    'success' => false,
                    'message' => 'An article with this title already exists.'
                ], 409);
            }

            return response()->json([
                'status_code' => 500,
                'success' => false,
                'message' => 'Failed to create help article. Please try again later.',
                'error' => $e->getMessage() // Include error message
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'success' => false,
                'message' => 'Failed to create help article. Please try again later.',
                'error' => $e->getMessage() // Include error message
            ], 500);
        }
    }

    public function update(Request $request, $articleId)
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'status_code' => 401,
                'success' => false,
                'message' => 'Authentication failed'
            ], 401);
        }

        // Validate the input
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'success' => false,
                'message' => 'Invalid input data.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $article = HelpArticle::find($articleId);

            if (!$article) {
                return response()->json([
                    'status_code' => 404,
                    'success' => false,
                    'message' => 'Help article not found.'
                ], 404);
            }

            // Check if the authenticated user is the author of the article
            if (Auth::id() !== $article->user_id) {
                return response()->json([
                    'status_code' => 403,
                    'success' => false,
                    'message' => 'You do not have permission to update this article.'
                ], 403);
            }

            $article->update($request->only(['title', 'content']));

            return response()->json([
                'status_code' => 200,
                'success' => true,
                'message' => 'Help article updated successfully.',
                'data' => $article
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'status_code' => 500,
                'success' => false,
                'message' => 'Failed to update help article. Please try again later.',
                'error' => $e->getMessage() // Include error message
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 500,
                'success' => false,
                'message' => 'Failed to update help article. Please try again later.',
                'error' => $e->getMessage() // Include error message
            ], 500);
        }
    }
}
