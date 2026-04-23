<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\BookingsModel;
use MongoDB\BSON\ObjectId;
use Carbon\Carbon;
class BookingsController extends Controller
{
    public function index(Request $request)
    {
        $bookings = BookingsModel::query()
            ->where('photographer_id', new ObjectId($request->user()->area_id))
            ->get()
            ->map(function ($b) {
                return [
                    'id' => (string) $b->id,
                    'photographer_id' => (string) $b->photographer_id,
                    'title' => $b->title,
                    'start' => $b->start,
                    'end' => $b->end,
                    'status' => $b->status,
                    'notes' => $b->notes,
                ];
            })
            ->toArray();
        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
        ]);
    }

    public function feed(Request $request)
    {
        $rangeStart = Carbon::parse($request->query('start'))->utc();
        $rangeEnd = Carbon::parse($request->query('end'))->utc();

        $events = BookingsModel::query()
            ->where('photographer_id', new ObjectId($request->user()->area_id))
            // return events that overlap the visible range
            ->where('start', '<', $rangeEnd)
            ->where('end', '>', $rangeStart)
            ->get()
            ->map(function ($b) {
                $start = $b->start?->toIso8601String();
                $end = $b->end?->toIso8601String();
                $status = (string) ($b->status ?? '');

                return [
                    'id' => (string) $b->id,
                    'title' => $b->title,
                    'start' => $start,
                    'end' => $end,
                    'extendedProps' => [
                        'status' => $status,
                        'notes' => (string) ($b->notes ?? ''),
                    ],
                ];
            })
            ->toArray();
        return response()->json($events);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $booking = new BookingsModel();
        $booking->photographer_id = new ObjectId($request->user()->area_id);
        $booking->title = $data['title'];
        $booking->start = Carbon::parse($data['start'])->utc();
        $booking->end = Carbon::parse($data['end'])->utc();
        $booking->status = $data['status'] ?? null;
        $booking->notes = $data['notes'] ?? null;
        $booking->save();

        return response()->json([
            'id' => (string) $booking->id,
        ], 201);
    }

    public function update(Request $request, string $booking)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'start' => ['sometimes', 'required', 'date'],
            'end' => ['sometimes', 'required', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $b = BookingsModel::query()
            ->where('_id', new ObjectId($booking))
            ->where('photographer_id', new ObjectId($request->user()->area_id))
            ->firstOrFail();

        if (array_key_exists('title', $data)) {
            $b->title = $data['title'];
        }
        if (array_key_exists('start', $data)) {
            $b->start = Carbon::parse($data['start'])->utc();
        }
        if (array_key_exists('end', $data)) {
            $b->end = Carbon::parse($data['end'])->utc();
        }

        // If both provided (or one changed), enforce ordering
        $start = $b->start ? Carbon::parse($b->start)->utc() : null;
        $end = $b->end ? Carbon::parse($b->end)->utc() : null;
        if ($start && $end && $end->lessThan($start)) {
            return response()->json([
                'message' => 'Invalid date range',
            ], 422);
        }

        if (array_key_exists('status', $data)) {
            $b->status = $data['status'];
        }
        if (array_key_exists('notes', $data)) {
            $b->notes = $data['notes'];
        }

        $b->save();

        return response()->json([
            'id' => (string) $b->id,
        ]);
    }

    public function destroy(Request $request, string $booking)
    {
        $b = BookingsModel::query()
            ->where('_id', new ObjectId($booking))
            ->where('photographer_id', new ObjectId($request->user()->area_id))
            ->firstOrFail();

        $b->delete();

        return response()->noContent();
    }
}
