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

namespace RoaveTest\SecurityAdvisories\AdvisorySources;

use Generator;
use PHPUnit\Framework\TestCase;
use Psl\Vec;
use Roave\SecurityAdvisories\Advisory;
use Roave\SecurityAdvisories\AdvisorySources\GetAdvisories;
use Roave\SecurityAdvisories\AdvisorySources\GetAdvisoriesAdvisoryRuleDecorator;

use function assert;
use function count;
use function method_exists;

/**
 * Tests for {@see \Roave\SecurityAdvisories\AdvisorySources\GetAdvisoriesAdvisoryRuleDecorator}
 *
 * @covers \Roave\SecurityAdvisories\AdvisorySources\GetAdvisoriesAdvisoryRuleDecorator
 */
class GetAdvisoriesAdvisoryRuleDecoratorTest extends TestCase
{
    public function testThatAdvisoriesAreDecoratedAfterBuiltFromYamlFilesAndConstraintIsChanged(): void
    {
        // Arrange
        $advisories = $this->getTempProvideGetAdvisories();

        $ruleToChangeLowerVersionConstraintRule =
            static function (Advisory $advisory): Advisory {
                $packageName = '3f/pygmentize';
                if ($advisory->package->packageName !== $packageName) {
                    return $advisory;
                }

                if ($advisory->getConstraint() !== '<1.2') {
                    return $advisory;
                }

                $config              = [];
                $config['reference'] = $packageName;
                $config['branches']  = [
                    '1.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['<1.1'],
                    ],
                    '2.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['>2.0'],
                    ],
                ];

                return Advisory::fromArrayData($config);
            };

        $changeOtherPackageConstraintRule =
            static function (Advisory $advisory): Advisory {
                $packageName = 'other/package-name';
                if ($advisory->package->packageName !== $packageName) {
                    return $advisory;
                }

                if ($advisory->getConstraint() !== '<2|>4') {
                    return $advisory;
                }

                $config              = [];
                $config['reference'] = $packageName;
                $config['branches']  = [
                    '1.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['>=3'],
                    ],
                ];

                return Advisory::fromArrayData($config);
            };

        $nullRule =
            static function (Advisory $advisory): Advisory {
                return $advisory;
            };

        // Act
        $decoratedAdvisories = (new GetAdvisoriesAdvisoryRuleDecorator(
            $advisories,
            [
                $changeOtherPackageConstraintRule,
                $nullRule,
                $ruleToChangeLowerVersionConstraintRule,
            ],
        ))();

        // Assert
        // check decorated handling and see version lowered and one branch added
        self::assertEquals([
            Advisory::fromArrayData([
                'branches' => [
                    '1.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['<1.1'],
                    ],
                    '2.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['>2.0'],
                    ],
                ],
                'reference' => 'composer://3f/pygmentize',
            ]),
            Advisory::fromArrayData([
                'branches' => [
                    '3.x' => [
                        'time' => '2012-05-15 09:09:00',
                        'versions' => ['>=3'],
                    ],
                ],
                'reference' => 'composer://other/package-name',
            ]),
        ], Vec\values($decoratedAdvisories));
    }

    public function testThatNothingBreaksWhenNoRulesAreProvided(): void
    {
        // Arrange
        $advisories = $this->getTempProvideGetAdvisories();

        $decoratedAdvisories = (new GetAdvisoriesAdvisoryRuleDecorator(
            $advisories,
            [],
        ));

        // Act
        $notDecoratedAdvisories = $advisories();
        $decoratedAdvisories    = $decoratedAdvisories();

        // Assert
        // check default handling
        self::assertEquals([
            Advisory::fromArrayData([
                'branches' => [
                    '1.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['<1.2'],
                    ],
                ],
                'reference' => 'composer://3f/pygmentize',
            ]),
            Advisory::fromArrayData([
                'branches' => [
                    '3.x' => [
                        'time' => '2012-05-15 09:09:00',
                        'versions' => ['<2|>4'],
                    ],
                ],
                'reference' => 'composer://other/package-name',
            ]),
        ], Vec\values($notDecoratedAdvisories));

        // check decorated handling and see version lowered and one branch added
        self::assertEquals([
            Advisory::fromArrayData([
                'branches' => [
                    '1.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['<1.2'],
                    ],
                ],
                'reference' => 'composer://3f/pygmentize',
            ]),
            Advisory::fromArrayData([
                'branches' => [
                    '3.x' => [
                        'time' => '2012-05-15 09:09:00',
                        'versions' => ['<2|>4'],
                    ],
                ],
                'reference' => 'composer://other/package-name',
            ]),
        ], Vec\values($decoratedAdvisories));
    }

    public function testThatRuleIsAddedToExpectedAdvisory(): void
    {
        // Arrange
        $ruleToChangeLowerVersionConstraintRule =
            static function (Advisory $advisory): Advisory {
                $packageName = '3f/pygmentize';
                if ($advisory->package->packageName !== $packageName) {
                    return $advisory;
                }

                if ($advisory->getConstraint() !== '<1.2') {
                    return $advisory;
                }

                $config              = [];
                $config['reference'] = $packageName;
                $config['branches']  = [
                    '1.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['<1.0|>2.0'],
                    ],
                ];

                return Advisory::fromArrayData($config);
            };

        $getAdvisories = $this->getTempProvideGetAdvisories();

        $decoratedAdvisories = (new GetAdvisoriesAdvisoryRuleDecorator(
            $getAdvisories,
            [$ruleToChangeLowerVersionConstraintRule],
        ));

        // Act
        $decoratedAdvisories = $decoratedAdvisories();

        // Assert
        // check decorated handling and see version lowered and one branch added
        self::assertEquals([
            Advisory::fromArrayData([
                'branches' => [
                    '1.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['<1.0|>2.0'],
                    ],
                ],
                'reference' => 'composer://3f/pygmentize',
            ]),
            Advisory::fromArrayData([
                'branches' => [
                    '3.x' => [
                        'time' => '2012-05-15 09:09:00',
                        'versions' => ['<2|>4'],
                    ],
                ],
                'reference' => 'composer://other/package-name',
            ]),
        ], Vec\values($decoratedAdvisories));
    }

    public function testThatRuleToOverwriteLaminasFormConstrainsWorksAsExpected(): void
    {
        // Arrange
        $ruleToFixLaminasFormConstraint =
            static function (Advisory $advisory): Advisory {
                $packageName      = 'laminas/laminas-form';
                $targetConstraint = '<2.17.2|>=3,<3.0.2|>=3.1,<3.1.1';

                if ($advisory->package->packageName !== $packageName) {
                    return $advisory;
                }

                if ($advisory->getConstraint() !== $targetConstraint) {
                    return $advisory;
                }

                $config              = [];
                $config['reference'] = $packageName;
                $config['branches']  = [
                    '2.17.x' => [
                        'versions' => ['<2.17.1'], // change constraint to <2.17.1
                    ],
                    '3.0.x' => [
                        'versions' => ['>=3','<3.0.2'],
                    ],
                    '3.1.x' => [
                        'versions' => ['>=3.1','<3.1.1'],
                    ],
                ];

                return Advisory::fromArrayData($config);
            };

        $getAdvisories = $this->getTempProvideGetAdvisories();

        assert(method_exists($getAdvisories, 'addAdvisory'));
        $getAdvisories->addAdvisory(
            Advisory::fromArrayData([
                'branches' => [
                    '2.17.x' => [
                        'versions' => ['<2.17.2'],
                    ],
                    '3.0.x' => [
                        'versions' => ['>=3','<3.0.2'],
                    ],
                    '3.1.x' => [
                        'versions' => ['>=3.1','<3.1.1'],
                    ],
                ],
                'reference' => 'composer://laminas/laminas-form',
            ]),
        );

        $decoratedAdvisories = (new GetAdvisoriesAdvisoryRuleDecorator(
            $getAdvisories,
            [$ruleToFixLaminasFormConstraint],
        ));

        // Act
        $decoratedAdvisories = $decoratedAdvisories();

        // Assert
        // check decorated handling and see version lowered and one branch added
        self::assertEquals([
            Advisory::fromArrayData([
                'branches' => [
                    '1.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['<1.2'],
                    ],
                ],
                'reference' => 'composer://3f/pygmentize',
            ]),
            Advisory::fromArrayData([
                'branches' => [
                    '3.x' => [
                        'time' => '2012-05-15 09:09:00',
                        'versions' => ['<2|>4'],
                    ],
                ],
                'reference' => 'composer://other/package-name',
            ]),
            Advisory::fromArrayData([
                'branches' => [
                    '2.17.x' => [
                        'versions' => ['<2.17.1'],
                    ],
                    '3.0.x' => [
                        'versions' => ['>=3','<3.0.2'],
                    ],
                    '3.1.x' => [
                        'versions' => ['>=3.1','<3.1.1'],
                    ],
                ],
                'reference' => 'composer://laminas/laminas-form',
            ]),
        ], Vec\values($decoratedAdvisories));
    }

    public function testThatRuleToOverwriteLaminasFormConstrainsWorksAsExpectedAndOnlyToTargetedConstraint(): void
    {
        // Arrange
        $ruleToFixLaminasFormConstraint =
            static function (Advisory $advisory): Advisory {
                $packageName      = 'laminas/laminas-form';
                $targetConstraint = '<2.17.2|>=3,<3.0.2|>=3.1,<3.1.1';

                if ($advisory->package->packageName !== $packageName) {
                    return $advisory;
                }

                if ($advisory->getConstraint() !== $targetConstraint) {
                    return $advisory;
                }

                $config              = [];
                $config['reference'] = $packageName;
                $config['branches']  = [
                    '2.17.x' => [
                        'versions' => ['<2.17.1'], // change constraint to <2.17.1
                    ],
                    '3.0.x' => [
                        'versions' => ['>=3','<3.0.2'],
                    ],
                    '3.1.x' => [
                        'versions' => ['>=3.1','<3.1.1'],
                    ],
                ];

                return Advisory::fromArrayData($config);
            };

        $getAdvisories = $this->getTempProvideGetAdvisories();

        assert(method_exists($getAdvisories, 'addAdvisory'));
        $getAdvisories->addAdvisory(
            Advisory::fromArrayData([
                'branches' => [
                    '2.17.x' => [
                        'versions' => ['<2.17.2'],
                    ],
                    '3.0.x' => [
                        'versions' => ['>=3','<3.0.2'],
                    ],
                    '3.1.x' => [
                        'versions' => ['>=3.1','<3.1.1'],
                    ],
                ],
                'reference' => 'composer://laminas/laminas-form',
            ]),
        );
        $getAdvisories->addAdvisory(
            Advisory::fromArrayData([
                'branches' => [
                    '3.0.x' => [
                        'versions' => ['>=3','<3.0.2'],
                    ],
                ],
                'reference' => 'composer://laminas/laminas-form',
            ]),
        );

        $decoratedAdvisories = (new GetAdvisoriesAdvisoryRuleDecorator(
            $getAdvisories,
            [$ruleToFixLaminasFormConstraint],
        ));

        // Act
        $decoratedAdvisories = $decoratedAdvisories();

        // Assert
        // check decorated handling and see version lowered and one branch added
        self::assertEquals([
            Advisory::fromArrayData([
                'branches' => [
                    '1.x' => [
                        'time' => '2017-05-15 09:09:00',
                        'versions' => ['<1.2'],
                    ],
                ],
                'reference' => 'composer://3f/pygmentize',
            ]),
            Advisory::fromArrayData([
                'branches' => [
                    '3.x' => [
                        'time' => '2012-05-15 09:09:00',
                        'versions' => ['<2|>4'],
                    ],
                ],
                'reference' => 'composer://other/package-name',
            ]),
            Advisory::fromArrayData([
                'branches' => [
                    '2.17.x' => [
                        'versions' => ['<2.17.1'],
                    ],
                    '3.0.x' => [
                        'versions' => ['>=3','<3.0.2'],
                    ],
                    '3.1.x' => [
                        'versions' => ['>=3.1','<3.1.1'],
                    ],
                ],
                'reference' => 'composer://laminas/laminas-form',
            ]),
            Advisory::fromArrayData([
                'branches' => [
                    '3.0.x' => [
                        'versions' => ['>=3','<3.0.2'],
                    ],
                ],
                'reference' => 'composer://laminas/laminas-form',
            ]),
        ], Vec\values($decoratedAdvisories));
    }

    private function getTempProvideGetAdvisories(): GetAdvisories
    {
        return new class implements GetAdvisories {
            /**
             * @param array<Advisory> $advisories
             */
            public function __construct(
                private array $advisories = []
            ) {
            }

            public function addAdvisory(Advisory $advisory): void
            {
                $this->advisories[] = $advisory;
            }

            /**
             * @return Generator<Advisory>
             */
            public function __invoke(): Generator
            {
                yield Advisory::fromArrayData([
                    'branches' => [
                        '1.x' => [
                            'time' => '2017-05-15 09:09:00',
                            'versions' => ['<1.2'],
                        ],
                    ],
                    'reference' => 'composer://3f/pygmentize',
                ]);

                yield Advisory::fromArrayData([
                    'branches' => [
                        '3.x' => [
                            'time' => '2012-05-15 09:09:00',
                            'versions' => ['<2|>4'],
                        ],
                    ],
                    'reference' => 'composer://other/package-name',
                ]);

                if (count($this->advisories) === 0) {
                    return;
                }

                foreach ($this->advisories as $advisory) {
                    yield $advisory;
                }
            }
        };
    }
}
