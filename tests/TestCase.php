<?php

namespace Tests;

use App\Models\ExamAttemptModel;
use App\Models\ExamModel;
use App\Models\ExamQuestionModel;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * MongoDB standalone does not support transactions.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = [];

    protected function setUp(): void
    {
        parent::setUp();

        ExamAttemptModel::query()->delete();
        ExamQuestionModel::query()->delete();
        ExamModel::query()->delete();
        User::query()->delete();
    }
}
