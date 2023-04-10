<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use PhpToken;

class NavigableToken extends PhpToken
{
    /**
     * @var Token|null
     */
    public $_prev;

    /**
     * @var Token|null
     */
    public $_next;

    /**
     * @var Token|null
     */
    public $_prevCode;

    /**
     * @var Token|null
     */
    public $_nextCode;

    /**
     * @var Token|null
     */
    public $_prevSibling;

    /**
     * @var Token|null
     */
    public $_nextSibling;

    public ?string $OriginalText = null;

    /**
     * Update the content of the token, setting OriginalText if needed
     *
     * @return $this
     */
    final public function setText(string $text)
    {
        if ($this->text !== $text) {
            $this->OriginalText = $this->OriginalText ?: $this->text;
            $this->text = $text;
        }

        return $this;
    }
}
