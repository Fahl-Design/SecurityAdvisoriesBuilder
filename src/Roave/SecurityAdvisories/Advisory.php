<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

declare(strict_types=1);

namespace Roave\SecurityAdvisories;

final class Advisory
{
    /**
     * @var string
     */
    private $componentName;

    /**
     * @var VersionConstraint[]
     */
    private $branchConstraints;

    /**
     * @param string              $componentName
     * @param VersionConstraint[] $branchConstraints
     */
    private function __construct(string $componentName, array $branchConstraints)
    {
        static $checkType;

        $checkType = $checkType ?: function (VersionConstraint ...$versionConstraints) {
            return $versionConstraints;
        };

        $this->componentName     = $componentName;
        $this->branchConstraints = $this->sortVersionConstraints($checkType(...$branchConstraints));
    }

    /**
     * @param array $config
     *
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArrayData(array $config) : self
    {
        // @TODO may want to throw exceptions on missing/invalid keys
        return new self(
            str_replace('composer://', '', $config['reference']),
            array_values(array_map(
                function (array $branchConfig) {
                    return VersionConstraint::fromString(implode(',', (array) $branchConfig['versions']));
                },
                $config['branches']
            ))
        );
    }

    public function getComponentName() : string
    {
        return $this->componentName;
    }

    /**
     * @return VersionConstraint[]
     */
    public function getVersionConstraints() : array
    {
        return $this->branchConstraints;
    }

    public function getConstraint() : ?string
    {
        // @TODO may want to escape this
        return implode(
            '|',
            array_map(
                function (VersionConstraint $versionConstraint) {
                    return $versionConstraint->getConstraintString();
                },
                $this->branchConstraints
            )
        ) ?: null;
    }

    /**
     * @param VersionConstraint[] $versionConstraints
     * @return VersionConstraint[]
     */
    private function sortVersionConstraints(array $versionConstraints): array
    {
        usort($versionConstraints, function(VersionConstraint $a, VersionConstraint $b) {
            $versionA = $a->getLowerBound() ?? $a->getUpperBound();
            $versionB = $b->getLowerBound() ?? $b->getUpperBound();

            if ($versionA && $versionB) {
                return $versionA->isGreaterOrEqualThan($versionB) ? 1 : -1;
            }

            return 0;
        });

        return $versionConstraints;
    }
}
