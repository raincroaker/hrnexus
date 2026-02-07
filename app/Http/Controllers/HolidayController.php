<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    private static function formatHolidayDate(mixed $date): string
    {
        return $date instanceof \DateTimeInterface
            ? Carbon::parse($date)->format('Y-m-d')
            : Carbon::parse((string) $date)->format('Y-m-d');
    }

    /**
     * List all holidays.
     */
    public function index(): JsonResponse
    {
        $holidays = Holiday::query()
            ->orderBy('date')
            ->get()
            ->map(fn (Holiday $h) => [
                'id' => $h->id,
                'name' => $h->name,
                'date' => self::formatHolidayDate($h->date),
            ]);

        return response()->json(['holidays' => $holidays]);
    }

    /**
     * Store a new holiday.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date', 'unique:holidays,date'],
        ]);

        $holiday = Holiday::create($validated);

        return response()->json([
            'message' => 'Holiday created successfully.',
            'holiday' => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'date' => self::formatHolidayDate($holiday->date),
            ],
        ], 201);
    }

    /**
     * Update the specified holiday.
     */
    public function update(Request $request, Holiday $holiday): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date', 'unique:holidays,date,' . $holiday->id],
        ]);

        $holiday->update($validated);

        return response()->json([
            'message' => 'Holiday updated successfully.',
            'holiday' => [
                'id' => $holiday->id,
                'name' => $holiday->name,
                'date' => self::formatHolidayDate($holiday->date),
            ],
        ]);
    }

    /**
     * Remove the specified holiday.
     */
    public function destroy(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return response()->json([
            'message' => 'Holiday deleted successfully.',
        ]);
    }
}
