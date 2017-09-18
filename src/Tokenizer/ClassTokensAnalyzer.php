<?php declare(strict_types=1);

namespace Symplify\CodingStandard\Tokenizer;

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;
use Symplify\CodingStandard\Exception\UnexpectedTokenException;
use Symplify\CodingStandard\FixerTokenWrapper\ArgumentWrapper;
use Symplify\CodingStandard\FixerTokenWrapper\PropertyAccessWrapper;
use Symplify\CodingStandard\FixerTokenWrapper\PropertyWrapper;

final class ClassTokensAnalyzer
{
    /**
     * @var int
     */
    private $endBracketIndex;

    /**
     * @var TokensAnalyzer
     */
    private $tokensAnalyzer;

    /**
     * @var Tokens
     */
    private $tokens;

    private function __construct(Tokens $tokens, int $startIndex)
    {
        $this->ensureIsClassyToken($tokens[$startIndex]);

        $this->startBracketIndex = $tokens->getNextTokenOfKind($startIndex, ['{']);
        $this->endBracketIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $this->startBracketIndex);

        $this->tokens = $tokens;
        $this->tokensAnalyzer = new TokensAnalyzer($tokens);
    }

    public static function createFromTokensArrayStartPosition(Tokens $tokens, int $startIndex): self
    {
        return new self($tokens, $startIndex);
    }

    /**
     * @return mixed[]
     */
    public function getPropertiesAndConstants(): array
    {
        return $this->filterClassyTokens($this->tokensAnalyzer->getClassyElements(), ['property', 'const']);
    }

    public function getClassEnd(): int
    {
        return $this->endBracketIndex;
    }

    /**
     * @return mixed[]
     */
    public function getProperties(): array
    {
        return $this->filterClassyTokens($this->tokensAnalyzer->getClassyElements(), ['property']);
    }

    public function getLastPropertyPosition(): ?int
    {
        $properties = $this->getProperties();
        if ($properties === []) {
            return null;
        }

        end($properties);

        return key($properties);
    }

    public function getFirstMethodPosition(): ?int
    {
        $methods = $this->getMethods();
        if ($methods === []) {
            return null;
        }

        end($methods);

        return key($methods);
    }

    /**
     * @return mixed[]
     */
    public function getMethods(): array
    {
        return $this->filterClassyTokens($this->tokensAnalyzer->getClassyElements(), ['method']);
    }

    public function renameEveryPropertyOccurrence(string $oldName, string $newName): void
    {
        for ($i = $this->startBracketIndex + 1; $i < $this->endBracketIndex; $i++) {
            $token = $this->tokens[$i];

            if ($token->isGivenKind(T_VARIABLE) === false) {
                continue;
            }

            if ($token->getContent() !== '$this') {
                continue;
            }

            $propertyAccessWrapper = PropertyAccessWrapper::createFromTokensAndPosition($this->tokens, $i);

            if ($propertyAccessWrapper->getName() === $oldName) {
                $propertyAccessWrapper->changeName($newName);
            }
        }
    }

    /**
     * @param mixed[] $classyElements
     * @param string[] $types
     * @return mixed[]
     */
    private function filterClassyTokens(array $classyElements, array $types): array
    {
        $filteredClassyTokens = [];

        foreach ($classyElements as $index => $classyToken) {
            if (! in_array($classyToken['type'], $types, true)) {
                continue;
            }

            $filteredClassyTokens[$index] = $classyToken;
        }

        return $filteredClassyTokens;
    }

    private function ensureIsClassyToken(Token $token): void
    {
        if ($token->isGivenKind([T_CLASS, T_INTERFACE, T_TRAIT])) {
            return;
        }

        throw new UnexpectedTokenException(sprintf(
            '"%s" expected "%s" token in its constructor. "%s" token given.',
            self::class,
            implode(',', ['T_CLASS', 'T_INTERFACE', 'T_TRAIT']),
            $token->getName()
        ));
    }
}