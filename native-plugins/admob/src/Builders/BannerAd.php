<?php

declare(strict_types=1);

namespace BlessedZulu\NativePhpAdmob\Builders;

class BannerAd extends AdBuilder
{
    public const FORMAT = 'banner';

    protected function format(): string
    {
        return self::FORMAT;
    }

    public function load(): self
    {
        $this->dispatch('Admob.LoadBanner');

        return $this;
    }

    /**
     * @param  string  $position  'bottom' (default) | 'top'
     * @param  int|null  $offset  extra gap (dp) from the screen edge to clear
     *                            chrome like a native bottom-nav. The configured
     *                            calibration is added to measured/null values;
     *                            zero explicitly opts out for desktop layouts.
     */
    public function show(string $position = 'bottom', ?int $offset = null): self
    {
        $calibration = $offset === 0 ? 0 : (int) config("admob.banner.offset.{$position}", 0);
        $offset = max(0, ($offset ?? 0) + $calibration);

        $this->dispatch('Admob.ShowBanner', [
            'position' => $position,
            'offset' => $offset,
            'safe_area' => (bool) config('admob.banner.safe_area', true),
        ]);

        return $this;
    }

    public function hide(): self
    {
        $this->dispatch('Admob.HideBanner');

        return $this;
    }
}
