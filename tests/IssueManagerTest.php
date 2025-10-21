<?php

namespace NDEstates\LaravelModelSchemaChecker\Tests;

use PHPUnit\Framework\TestCase;
use NDEstates\LaravelModelSchemaChecker\Services\IssueManager;

class IssueManagerTest extends TestCase
{
    private IssueManager $issueManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->issueManager = new IssueManager();
    }

    public function test_issue_manager_can_be_instantiated()
    {
        $this->assertInstanceOf(IssueManager::class, $this->issueManager);
    }

    public function test_issue_manager_starts_empty()
    {
        $this->assertEmpty($this->issueManager->getIssues());
        $this->assertEquals(0, $this->issueManager->count());
        $this->assertFalse($this->issueManager->hasIssues());
    }

    public function test_issue_manager_can_add_single_issue()
    {
        $this->issueManager->addIssue('model', 'fillable_missing', [
            'message' => 'Fillable property missing',
            'severity' => 'high',
            'file' => 'User.php',
        ]);

        $issues = $this->issueManager->getIssues();
        $this->assertCount(1, $issues);
        $this->assertEquals(1, $this->issueManager->count());
        $this->assertTrue($this->issueManager->hasIssues());

        $issue = $issues[0];
        $this->assertEquals('model', $issue['category']);
        $this->assertEquals('fillable_missing', $issue['type']);
        $this->assertEquals('Fillable property missing', $issue['message']);
        $this->assertEquals('high', $issue['severity']);
        $this->assertEquals('User.php', $issue['file']);
        $this->assertArrayHasKey('timestamp', $issue);
    }

    public function test_issue_manager_can_add_multiple_issues()
    {
        $issues = [
            [
                'category' => 'model',
                'type' => 'fillable_missing',
                'message' => 'Fillable property missing',
                'severity' => 'high',
            ],
            [
                'category' => 'migration',
                'type' => 'syntax_error',
                'message' => 'Syntax error in migration',
                'severity' => 'critical',
            ],
        ];

        $this->issueManager->addIssues($issues);

        $this->assertEquals(2, $this->issueManager->count());
        $this->assertTrue($this->issueManager->hasIssues());
    }

    public function test_issue_manager_tracks_statistics()
    {
        $this->issueManager->addIssue('model', 'fillable_missing', [
            'severity' => 'high',
        ]);
        $this->issueManager->addIssue('model', 'relationship_missing', [
            'severity' => 'medium',
        ]);
        $this->issueManager->addIssue('migration', 'syntax_error', [
            'severity' => 'critical',
        ]);

        $stats = $this->issueManager->getStats();

        $this->assertEquals(3, $stats['total_issues']);
        $this->assertEquals(2, $stats['issues_by_category']['model']);
        $this->assertEquals(1, $stats['issues_by_category']['migration']);
        $this->assertEquals(1, $stats['issues_by_type']['fillable_missing']);
        $this->assertEquals(1, $stats['issues_by_type']['relationship_missing']);
        $this->assertEquals(1, $stats['issues_by_type']['syntax_error']);
        $this->assertEquals(1, $stats['issues_by_severity']['high']);
        $this->assertEquals(1, $stats['issues_by_severity']['medium']);
        $this->assertEquals(1, $stats['issues_by_severity']['critical']);
    }

    public function test_issue_manager_can_filter_issues_by_category()
    {
        $this->issueManager->addIssue('model', 'fillable_missing', ['message' => 'Issue 1']);
        $this->issueManager->addIssue('migration', 'syntax_error', ['message' => 'Issue 2']);
        $this->issueManager->addIssue('model', 'relationship_missing', ['message' => 'Issue 3']);

        $modelIssues = $this->issueManager->getIssuesByCategory('model');
        $migrationIssues = $this->issueManager->getIssuesByCategory('migration');

        $this->assertCount(2, $modelIssues);
        $this->assertCount(1, $migrationIssues);

        // Check that the messages are present in the returned issues
        $modelMessages = array_column($modelIssues, 'message');
        $migrationMessages = array_column($migrationIssues, 'message');

        $this->assertContains('Issue 1', $modelMessages);
        $this->assertContains('Issue 3', $modelMessages);
        $this->assertContains('Issue 2', $migrationMessages);
    }

    public function test_issue_manager_can_filter_issues_by_type()
    {
        $this->issueManager->addIssue('model', 'fillable_missing', ['message' => 'Issue 1']);
        $this->issueManager->addIssue('migration', 'fillable_missing', ['message' => 'Issue 2']);
        $this->issueManager->addIssue('model', 'relationship_missing', ['message' => 'Issue 3']);

        $fillableIssues = $this->issueManager->getIssuesByType('fillable_missing');
        $relationshipIssues = $this->issueManager->getIssuesByType('relationship_missing');

        $this->assertCount(2, $fillableIssues);
        $this->assertCount(1, $relationshipIssues);

        // Check that the messages are present in the returned issues
        $fillableMessages = array_column($fillableIssues, 'message');
        $relationshipMessages = array_column($relationshipIssues, 'message');

        $this->assertContains('Issue 1', $fillableMessages);
        $this->assertContains('Issue 2', $fillableMessages);
        $this->assertContains('Issue 3', $relationshipMessages);
    }

    public function test_issue_manager_can_filter_issues_by_severity()
    {
        $this->issueManager->addIssue('model', 'fillable_missing', [
            'message' => 'Issue 1',
            'severity' => 'high',
        ]);
        $this->issueManager->addIssue('migration', 'syntax_error', [
            'message' => 'Issue 2',
            'severity' => 'critical',
        ]);
        $this->issueManager->addIssue('model', 'relationship_missing', [
            'message' => 'Issue 3',
            'severity' => 'high',
        ]);

        $highIssues = $this->issueManager->getIssuesBySeverity('high');
        $criticalIssues = $this->issueManager->getIssuesBySeverity('critical');
        $mediumIssues = $this->issueManager->getIssuesBySeverity('medium');

        $this->assertCount(2, $highIssues);
        $this->assertCount(1, $criticalIssues);
        $this->assertCount(0, $mediumIssues); // No medium severity issues
    }

    public function test_issue_manager_handles_issues_without_severity()
    {
        $this->issueManager->addIssue('model', 'fillable_missing', [
            'message' => 'Issue without severity',
        ]);

        $mediumIssues = $this->issueManager->getIssuesBySeverity('medium');

        $this->assertCount(1, $mediumIssues);
        $this->assertEquals('Issue without severity', $mediumIssues[0]['message']);
    }

    public function test_issue_manager_can_attach_improvement_to_last_issue()
    {
        $this->issueManager->addIssue('model', 'fillable_missing', [
            'message' => 'Issue 1',
        ]);
        $this->issueManager->addIssue('model', 'relationship_missing', [
            'message' => 'Issue 2',
        ]);

        $improvement = [
            'type' => 'add_fillable',
            'code' => "protected \$fillable = ['name', 'email'];",
        ];

        $this->issueManager->attachImprovementToLastIssue($improvement);

        $issues = $this->issueManager->getIssues();
        $this->assertArrayHasKey('improvement', $issues[1]);
        $this->assertEquals($improvement, $issues[1]['improvement']);

        // First issue should not have improvement
        $this->assertArrayNotHasKey('improvement', $issues[0]);
    }

    public function test_issue_manager_attach_improvement_does_nothing_when_no_issues()
    {
        $improvement = ['type' => 'test'];
        $this->issueManager->attachImprovementToLastIssue($improvement);

        // Should not throw any errors
        $this->assertEmpty($this->issueManager->getIssues());
    }

    public function test_issue_manager_statistics_handle_missing_severity()
    {
        $this->issueManager->addIssue('model', 'test', []);

        $stats = $this->issueManager->getStats();
        $this->assertEquals(1, $stats['total_issues']);
        // Issues without severity should not be counted in severity stats
        $this->assertEmpty($stats['issues_by_severity']);
    }

    public function test_issue_manager_add_issues_filters_invalid_issues()
    {
        $issues = [
            [
                'category' => 'model',
                'type' => 'valid_issue',
                'message' => 'Valid issue',
            ],
            [
                'message' => 'Invalid issue - missing category and type',
            ],
            [
                'category' => 'model',
                // Missing type
                'message' => 'Invalid issue - missing type',
            ],
        ];

        $this->issueManager->addIssues($issues);

        $this->assertEquals(1, $this->issueManager->count());
        $issues = $this->issueManager->getIssues();
        $this->assertEquals('Valid issue', $issues[0]['message']);
    }
}