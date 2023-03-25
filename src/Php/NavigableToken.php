<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use PhpToken;

class NavigableToken extends PhpToken
{
    /**
     * @var Token|null
     */
    protected $_prev;

    /**
     * @var Token|null
     */
    protected $_next;

    /**
     * @var Token|null
     */
    protected $_prevCode;

    /**
     * @var Token|null
     */
    protected $_nextCode;

    /**
     * @var Token|null
     */
    protected $_prevSibling;

    /**
     * @var Token|null
     */
    protected $_nextSibling;

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
            $this->text         = $text;
        }

        return $this;
    }
}
