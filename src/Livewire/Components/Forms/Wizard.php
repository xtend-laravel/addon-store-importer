<?php

namespace XtendLunar\Addons\StoreImporter\Livewire\Components\Forms;

class Wizard
{
    protected array $steps = [];

    protected int $currentStep = 0;

    public static function make(): self
    {
        return new static();
    }

    public function nextStep(): void
    {
        if ($this->currentStep === count($this->steps) - 1) {
            return;
        }

        $this->currentStep++;
    }

    public function previousStep(): void
    {
        if ($this->currentStep === 0) {
            return;
        }

        $this->currentStep--;
    }

    public function steps(array $steps): self
    {
        $this->steps = $steps;

        return $this;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function currentStep(): int
    {
        return $this->currentStep;
    }
}
