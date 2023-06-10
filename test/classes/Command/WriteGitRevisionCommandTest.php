<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Command;

use PhpMyAdmin\Command\WriteGitRevisionCommand;
use PhpMyAdmin\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Command\Command;

use function class_exists;
use function sprintf;

#[CoversClass(WriteGitRevisionCommand::class)]
class WriteGitRevisionCommandTest extends AbstractTestCase
{
    private WriteGitRevisionCommand $command;

    public function testGetGeneratedClassValidVersion(): void
    {
        if (! class_exists(Command::class)) {
            $this->markTestSkipped('The Symfony Console is missing');
        }

        $this->command = $this->getMockBuilder(WriteGitRevisionCommand::class)
            ->onlyMethods(['gitCli'])
            ->getMock();

        $this->command->expects($this->exactly(3))->method('gitCli')->willReturnMap([
            ['describe --always', 'RELEASE_5_1_0-638-g1c018e2a6c'],
            ['log -1 --format="%H"', '1c018e2a6c6d518c4a2dde059e49f33af67c4636'],
            ['symbolic-ref -q HEAD', 'refs/heads/cli-rev-info'],
        ]);

        $output = $this->callFunction(
            $this->command,
            WriteGitRevisionCommand::class,
            'getRevisionInfo',
            ['https://github.com/phpmyadmin/phpmyadmin/commit/%s', 'https://github.com/phpmyadmin/phpmyadmin/tree/%s'],
        );
        $template = <<<'PHP'
<?php

declare(strict_types=1);

/**
 * This file is generated by scripts/console.
 *
 * @see \PhpMyAdmin\Command\WriteGitRevisionCommand
 */
return [
    'revision' => '%s',
    'revisionUrl' => '%s',
    'branch' => '%s',
    'branchUrl' => '%s',
];

PHP;
        $this->assertSame(
            sprintf(
                $template,
                'RELEASE_5_1_0-638-g1c018e2a6c',
                'https://github.com/phpmyadmin/phpmyadmin/commit/1c018e2a6c6d518c4a2dde059e49f33af67c4636',
                'cli-rev-info',
                'https://github.com/phpmyadmin/phpmyadmin/tree/cli-rev-info',
            ),
            $output,
        );
    }
}
