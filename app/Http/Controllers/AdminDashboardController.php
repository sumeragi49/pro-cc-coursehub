<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'totalStudents' => User::where('role', 'student')->count(),
            'totalCoaches' => User::where('role', 'coach')->count(),
            'totalCourses' => Course::count(),
            'publishedCourses' => Course::published()->count(),
            'totalEnrollments' => Enrollment::count(),
        ]);
    }
}
