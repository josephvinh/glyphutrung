/**
 * TNTT Agent Team Workflow
 * 
 * Orchestrates multiple agents to work on tasks:
 * 1. Analyzer - Create SPEC from requirements
 * 2. Coder - Implement the code
 * 3. Tester - Write and run tests
 * 4. Reviewer - Final code review
 */

const fs = require('fs');
const path = require('path');

// Colors for output
const RED = '\x1b[31m';
const GREEN = '\x1b[32m';
const YELLOW = '\x1b[33m';
const BLUE = '\x1b[34m';
const CYAN = '\x1b[36m';
const RESET = '\x1b[0m';
const BRIGHT = '\x1b[1m';

const AGENTS = {
    analyzer: { name: 'TNTT Analyzer', model: 'opus', file: '.claude/agents/analyzer.md', color: CYAN },
    coder: { name: 'TNTT Coder', model: 'sonnet', file: '.claude/agents/coder.md', color: GREEN },
    tester: { name: 'TNTT Tester', model: 'sonnet', file: '.claude/agents/tester.md', color: YELLOW },
    reviewer: { name: 'TNTT Reviewer', model: 'opus', file: '.claude/agents/reviewer.md', color: BLUE },
    security: { name: 'TNTT Security', model: 'opus', file: '.claude/agents/security.md', color: RED },
    devops: { name: 'TNTT DevOps', model: 'sonnet', file: '.claude/agents/devops.md', color: CYAN },
    documenter: { name: 'TNTT Documenter', model: 'haiku', file: '.claude/agents/documenter.md', color: RESET }
};

function runDemo() {
    console.log('\n' + BRIGHT + CYAN + '========================================');
    console.log('    TNTT Agent Team - Demo Workflow');
    console.log('========================================' + RESET + '\n');
    
    console.log(GREEN + 'Agent Workflow Sequence:' + RESET);
    console.log('----------------------');
    console.log('1. ANALYZER -> Creates SPEC from requirements');
    console.log('2. CODER -> Implements the code');
    console.log('3. TESTER -> Writes and runs tests');
    console.log('4. REVIEWER -> Final code review');
    console.log('5. SECURITY -> Security audit (if needed)');
    console.log('6. DEVOPS -> Build and deployment');
    console.log('7. DOCUMENTER -> Update documentation\n');
    
    console.log(GREEN + 'Available Agents:' + RESET);
    Object.entries(AGENTS).forEach(([key, agent]) => {
        console.log('  ' + agent.color + key.padEnd(12) + RESET + ' ' + agent.name + ' (' + agent.model + ')');
    });
    
    console.log('\n' + GREEN + 'How to Call in Claude Code:' + RESET);
    console.log('---------------------------');
    console.log('@agent description="Analyze feature" model="opus" ...');
}

function showHelp() {
    console.log('\nTNTT Agent Team Workflow\n');
    console.log('Usage: node agent-team.js [command]\n');
    console.log('Commands:');
    console.log('  demo    Run demo workflow');
    console.log('  list    List all agents');
    console.log('  help    Show this help\n');
    console.log('Agents: ' + Object.keys(AGENTS).join(', '));
}

const args = process.argv.slice(2);
const command = args[0] || 'help';

switch (command) {
    case 'demo': runDemo(); break;
    case 'list':
        console.log('\nAvailable Agents:');
        Object.entries(AGENTS).forEach(([key, agent]) => {
            console.log('  ' + agent.color + key.padEnd(12) + RESET + ' ' + agent.name + ' (' + agent.model + ')');
        });
        break;
    default: showHelp();
}
