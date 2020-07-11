<?php declare(strict_types=1);

namespace App\FeedFetcher;

use App\Model\SocialNetworkProfile;

class FetchResult
{
    protected SocialNetworkProfile $socialNetworkProfile;
    protected string $status;

    protected int $counterFetched = 0;
    protected int $counterRabbit = 0;
    protected int $counterPushed200 = 0;
    protected int $counterPushed4xx = 0;
    protected int $counterPushed5xx = 0;

    public function getSocialNetworkProfile(): SocialNetworkProfile
    {
        return $this->socialNetworkProfile;
    }

    public function setSocialNetworkProfile(SocialNetworkProfile $socialNetworkProfile): FetchResult
    {
        $this->socialNetworkProfile = $socialNetworkProfile;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): FetchResult
    {
        $this->status = $status;

        return $this;
    }

    public function getCounterFetched(): int
    {
        return $this->counterFetched;
    }

    public function setCounterFetched(int $counterFetched): FetchResult
    {
        $this->counterFetched = $counterFetched;

        return $this;
    }

    public function getCounterPushed200(): int
    {
        return $this->counterPushed200;
    }

    public function setCounterPushed200(int $counterPushed200): FetchResult
    {
        $this->counterPushed200 = $counterPushed200;

        return $this;
    }

    public function incCounterPushed200(): FetchResult
    {
        ++$this->counterPushed200;

        return $this;
    }

    public function getCounterPushed4xx(): int
    {
        return $this->counterPushed4xx;
    }

    public function setCounterPushed4xx(int $counterPushed4xx): FetchResult
    {
        $this->counterPushed4xx = $counterPushed4xx;

        return $this;
    }

    public function incCounterPushed4xx(): FetchResult
    {
        ++$this->counterPushed4xx;

        return $this;
    }

    public function getCounterPushed5xx(): int
    {
        return $this->counterPushed5xx;
    }

    public function setCounterPushed5xx(int $counterPushed5xx): FetchResult
    {
        $this->counterPushed5xx = $counterPushed5xx;

        return $this;
    }

    public function incCounterPushed5xx(): FetchResult
    {
        ++$this->counterPushed5xx;

        return $this;
    }

    public function getCounterRabbit(): int
    {
        return $this->counterRabbit;
    }

    public function setCounterRabbit(int $counterRabbit): FetchResult
    {
        $this->counterRabbit = $counterRabbit;

        return $this;
    }

    public function incCounterRabbit(): FetchResult
    {
        ++$this->counterRabbit;

        return $this;
    }
}
