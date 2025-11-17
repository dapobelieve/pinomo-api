<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait HttpResponseTrait
{
  /**
   * Send a success response.
   *
   * @param mixed $data
   * @param string $message
   * @param int $status
   * @return JsonResponse
   */
  public function successResponse($data = null, string $message = 'Success', int $status = 200): JsonResponse
  {
    return response()->json([
      'success' => true,
      'message' => $message,
      'data' => $data,
    ], $status);
  }

  /**
   * Send an error response.
   *
   * @param string $message
   * @param int $status
   * @param mixed $errors
   * @return JsonResponse
   */
  public function errorResponse(string $message = 'Error', int $status = 400, $errors = null): JsonResponse
  {
    return response()->json([
      'success' => false,
      'message' => $message,
      'errors' => $errors,
    ], $status);
  }

  /**
   * Send a validation error response.
   *
   * @param array $errors
   * @param string $message
   * @return JsonResponse
   */
  public function validationErrorResponse(array $errors, string $message = 'Validation Error'): JsonResponse
  {
    return $this->errorResponse($message, 422, $errors);
  }
}
