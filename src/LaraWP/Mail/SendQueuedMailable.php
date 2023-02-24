<?php

namespace LaraWP\Mail;

use LaraWP\Bus\Queueable;
use LaraWP\Contracts\Mail\Factory as MailFactory;
use LaraWP\Contracts\Mail\Mailable as MailableContract;
use LaraWP\Contracts\Queue\ShouldBeEncrypted;

class SendQueuedMailable
{
    use Queueable;

    /**
     * The mailable message instance.
     *
     * @var \LaraWP\Contracts\Mail\Mailable
     */
    public $mailable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * Indicates if the job should be encrypted.
     *
     * @var bool
     */
    public $shouldBeEncrypted = false;

    /**
     * Create a new job instance.
     *
     * @param \LaraWP\Contracts\Mail\Mailable $mailable
     * @return void
     */
    public function __construct(MailableContract $mailable)
    {
        $this->mailable = $mailable;
        $this->tries = property_exists($mailable, 'tries') ? $mailable->tries : null;
        $this->timeout = property_exists($mailable, 'timeout') ? $mailable->timeout : null;
        $this->afterCommit = property_exists($mailable, 'afterCommit') ? $mailable->afterCommit : null;
        $this->shouldBeEncrypted = $mailable instanceof ShouldBeEncrypted;
    }

    /**
     * Handle the queued job.
     *
     * @param \LaraWP\Contracts\Mail\Factory $factory
     * @return void
     */
    public function handle(MailFactory $factory)
    {
        $this->mailable->send($factory);
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName()
    {
        return get_class($this->mailable);
    }

    /**
     * Call the failed method on the mailable instance.
     *
     * @param \Throwable $e
     * @return void
     */
    public function failed($e)
    {
        if (method_exists($this->mailable, 'failed')) {
            $this->mailable->failed($e);
        }
    }

    /**
     * Get the number of seconds before a released mailable will be available.
     *
     * @return mixed
     */
    public function backoff()
    {
        if (!method_exists($this->mailable, 'backoff') && !isset($this->mailable->backoff)) {
            return;
        }

        return $this->mailable->backoff ?? $this->mailable->backoff();
    }

    /**
     * Prepare the instance for cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->mailable = clone $this->mailable;
    }
}
