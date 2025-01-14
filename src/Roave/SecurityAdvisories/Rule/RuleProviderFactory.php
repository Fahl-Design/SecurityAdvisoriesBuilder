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

namespace Roave\SecurityAdvisories\Rule;

use Roave\SecurityAdvisories\Advisory;

final class RuleProviderFactory
{
    /**
     * @psalm-return list<callable(Advisory): Advisory>
     */
    public function __invoke(): array
    {
        return [
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
            },
        ];
    }
}
