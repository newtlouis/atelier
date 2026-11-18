# atelier

[![CI](https://github.com/newtlouis/atelier/actions/workflows/ci.yml/badge.svg)](https://github.com/newtlouis/atelier/actions/workflows/ci.yml)

**A free-text customer request becomes a priced, traceable, auditable quote.**

Back-office for a furniture workshop: a customer describes what they want in a few
sentences, the application extracts a structured request from it, prices it, and issues
a quote and then an invoice.

> **Work in progress.** This repository has been public since day one: the skeleton runs
> and CI passes. Features land batch by batch — see [the journal](JOURNAL.md).
> *(Version française de ce README : [README.fr.md](README.fr.md).)*

## The LLM part, and what keeps it honest

The model reads text. It never decides a price. Everything below is in
[`src/Extraction/`](src/Extraction).

- **Structured output with a versioned schema.** The JSON schema is written by hand
  rather than derived from a PHP class, because `SchemaExtraction::VERSION` is data an
  evaluation report has to be able to quote. Every logged call carries the prompt version
  that produced it.
- **Validation lives after the call.** A structured output guarantees the shape of a
  response, never its coherence: nothing stops a model from returning a length of
  -500 mm. [`LecteurDeReponse`](src/Extraction/Infrastructure/Claude/LecteurDeReponse.php)
  (*"response reader"*) rejects what no JSON schema can express, and it is isolated from
  the network call so it can be tested against dozens of malformed responses without
  spending a cent.
- **Indirect prompt injection is treated as the default case.** The customer text is
  never concatenated into the instructions. It travels in its own user message, inside an
  explicit delimiter, with a reminder *after* the text that what it contains is data and
  not instructions — last instruction read carries the most weight. A model refusal and a
  service outage are both handled as ordinary outcomes, not as exceptions to fix later.
- **Every call is costed and timed.** [`Extraction/Cout/`](src/Extraction/Cout)
  (*"cost"*) logs the model, the prompt version, the latency, the failure if any, and four
  separate token counters — input, output, cache write, cache read — because they do not
  cost the same. Prices live in a per-model table in nano-dollars, integers only. One
  extraction runs at roughly 1.2 ¢ on Opus 5.5.
- **The cheapest call is the one you do not make.**
  [`Aiguillage`](src/Extraction/Aiguillage.php) (*"routing"*) answers without the model
  when the answer is already known — a catalogue order has a fixed price, and a message
  that reduces to dimensions is parsed directly. Anything else, down to one extra word,
  goes to the model with the form fields attached.

**Next up:** an evaluation set over golden cases, so that a change of prompt, model or
effort level can be shown to improve something rather than assumed to. Half the cases are
deliberately hostile: incomplete, contradictory, off-topic, or carrying an injection
attempt.

## Getting started

```bash
make up     # build, start, install dependencies → http://localhost:8080
make test   # the test suite
```

`make` on its own lists the available commands. Nothing is installed locally beyond a
container engine (Docker or OrbStack): PHP 8.4 and PostgreSQL 16 run in containers.

## What is already in place

- Symfony 7.4 LTS on PHP 8.4, served by FrankenPHP
- PostgreSQL 16
- PHPStan **level 10**
- Deptrac: one domain calling into another fails CI
- **Infection: MSI 100 %** — the suite catches 203 mutations out of 203
- PHP CS Fixer, `composer audit`
- CI that runs **exactly** the commands above

## Why mutation testing rather than coverage

Coverage tells you which lines ran, not whether anyone was watching. A suite can cover
100 % of the code while asserting nothing.

Infection changes the code — turns a `<=` into a `<`, flips a ternary, drops a call — and
checks that a test complains. On this repository it found seven holes invisible to
coverage, including a VAT rule duplicated across two calculators that would eventually
have drifted apart. `make mutation` to run it again.

## The architecture, in one line

Two domains — `Extraction` and `Devis` (*quotes*) — that never talk to each other
directly: each one knows only `Shared`, and only `Application` is allowed to know two at
once. The rule is enforced mechanically by Deptrac, not documented in a wiki.

## The domain declares, the infrastructure implements

```
src/Extraction/
├── Extracteur.php            ← the interface: one method, no technical dependency
├── Cout/                     ← what the domain knows about itself: tokens, pricing, log
└── Infrastructure/
    ├── Claude/               ← the Anthropic SDK is found nowhere else
    ├── JournalDoctrine.php
    └── ExtracteurFactice.php ← the test double
```

`Extracteur` does not know `ExtracteurClaude`. This is not a convention: Deptrac treats
`Extraction` and `Extraction/Infrastructure` as two layers, and only the second may know
the first. Naming the adapter from the port breaks CI — verified by causing the violation
before writing this line.

The implementation is chosen once, in `config/services.yaml`; in tests,
`config/services_test.yaml` switches to a double, which guarantees that no suite can ever
trigger a billed call.

**What is deliberately not separated:** the `Devis` entities carry their Doctrine
attributes, and `DevisRepository` is a Doctrine repository. A separate persistence model
would double the code for no gain here. The rule actually applied is narrower, and it is
the one that holds: **a port where the implementation can change or cost money** — the
model provider — not everywhere on principle.

## Built with agents, under control

A good part of this code is written by Claude Code. What matters is not who types, it is
**what stops wrong code from reaching `main`** — and what makes sure the right thing got
built. All of it is versioned in [`.claude/`](.claude):

| Stage | What holds it | Where |
|---|---|---|
| Frame | One sheet per feature: problem, evidence, measurable success criterion, appetite, out of scope. Approved before the first line of code. | `/feature`, `docs/fiches/` |
| Write | After each file: style fixed, PHPStan level 10 on that file. Before each file: refusal of a float in money code, of a call into the AI domain from the quote domain, of a `@phpstan-ignore`. | `.claude/hooks/` |
| Close | The agent cannot hand back control on a red repository: tests, PHPStan, Deptrac and style run at the end of every turn. | `barriere-fin-de-tour.sh` |
| Review | Two specialised reviewers in parallel (security, architecture), and a third that runs Infection on the touched files and writes the missing tests. Every finding is verified before it is kept. | `.claude/agents/`, `/revue` |
| Parallelise | One batch = one worktree with its own Docker stack on a free port. | `make bac-a-sable NOM=…` |
| Ship | CI replays the commands from this README; a Claude review comments on every pull request. | `.github/workflows/` |
| Measure | On the date set in the sheet, go and get the data, then decide: keep, adjust, drop. | `/bilan` |

**What the agent cannot do, by construction rather than by instruction:** push, read the
API key, destroy database volumes, regenerate a PHPStan baseline, disable a test, or
modify `deptrac.yaml`, `phpstan.neon`, `infection.json5` and CI without explicit approval.
An agent that can lower the bar it is judged against eventually will. Permission bypass
mode is disabled in this repository.

## Naming: French or English?

Both, under one rule: **the vocabulary of the business stays in the language of the
business, the technical skeleton is in English.**

`Devis`, `Facture`, `Bareme`, `Montant`, `Chiffrage` are French because they are concepts
of French law: a *devis* is not a quote, and invoice numbering follows article 242 nonies A
of the French tax code. Translating them would add a layer of translation between what the
accountant says and what the code says.

`Shared`, `Http`, `Controller`, `Kernel` are English because they name nothing in the
business: they are architecture and framework terms.

Corollary: **no path straddling the two languages.** `Shared/Argent/Montant` existed for
half a day before being corrected to `Shared/Montant`.

Comments and documentation are moving to English file by file, starting with the
extraction chain. The journal of past decisions ([`JOURNAL.md`](JOURNAL.md)) is still in
French.

## Licence

MIT.
