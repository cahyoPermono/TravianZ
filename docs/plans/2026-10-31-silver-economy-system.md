# 5-Pillar Silver Economy System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement a complete, balanced 5-pillar Silver economy in TravianZ allowing players to naturally earn Silver through Hero Item Scrapping, Weekly Medals, Oasis Hunting, Daily Quests, and Bandit World Boss bounty sharing.

**Architecture:** Modulate each feature into decoupled, self-contained components interacting with `s1_hero.silver` via `BlackMarket::addSilver()`:
1. `BlackMarket::sellHeroItemForSilver()` for inventory liquidation to NPC.
2. `AutomationMedals` hook for weekly Top 10 Silver payouts.
3. `OasisBounty` service hooking into `AutomationBattleResolution` with a 150 Silver daily cap.
4. `DailyQuests` service tracking 4 daily milestones (Adventure, Training, Building, Raid) with a 50 Silver milestone chest and `#side_info` UI widget.
5. `BanditCamp` contribution tracking splitting the 4,000 Silver World Boss bounty among all participating attackers.

**Tech Stack:** PHP 7.4+, MySQL / MariaDB, HTML/CSS for responsive Travian side widget, PHPUnit test scripts.

---

### Task 1: Hero Item Pawn Shop for Silver (`BlackMarket.php` & UI)

**Files:**
- Modify: `GameEngine/BlackMarket.php`
- Modify: `Templates/blackmarket.tpl`
- Test: `tests/HeroItemPawnSilverTest.php`

**Step 1: Write the failing test**
Create `tests/HeroItemPawnSilverTest.php` to verify:
- Selling an unequipped item (e.g. 5x Cages or 1x Tier 1 Sword) awards exact Silver to player's `silver` balance.
- Equipped items or items not owned cannot be sold.
- Quantity boundary checks are enforced.

**Step 2: Run test to verify it fails**
Run: `docker exec travianz-web php tests/HeroItemPawnSilverTest.php`
Expected: FAIL (`sellHeroItemForSilver` not defined).

**Step 3: Implement minimal code**
In `GameEngine/BlackMarket.php`:
- Implement `getItemSilverAppraisal(int $itemId, int $qty): array`
- Implement `sellHeroItemForSilver(int $uid, int $vref, int $rowId, int $quantity = 1): array`
- In `Templates/blackmarket.tpl`, provide UI toggle / button: "Gadai ke Pasar Gelap (Silver)".

**Step 4: Run test to verify it passes**
Run: `docker exec travianz-web php tests/HeroItemPawnSilverTest.php`
Expected: PASS.

**Step 5: Commit**
```bash
git add GameEngine/BlackMarket.php Templates/blackmarket.tpl tests/HeroItemPawnSilverTest.php
git commit -m "feat(silver): add hero item pawn shop for silver in Black Market"
```

---

### Task 2: Weekly Medal Silver Rewards (`AutomationMedals.php`)

**Files:**
- Modify: `GameEngine/Automation/AutomationMedals.php`
- Test: `tests/WeeklyMedalsSilverRewardTest.php`

**Step 1: Write the failing test**
Create `tests/WeeklyMedalsSilverRewardTest.php`:
- Verify that when `awardTopMedals()` executes, users ranked 1 receive +300 Silver, rank 2-3 receive +150 Silver, and rank 4-10 receive +50 Silver.
- Verify user receives in-game message/notification of reward.

**Step 2: Run test to verify it fails**
Run: `docker exec travianz-web php tests/WeeklyMedalsSilverRewardTest.php`
Expected: FAIL.

**Step 3: Implement minimal code**
In `GameEngine/Automation/AutomationMedals.php`:
- In `awardTopMedals()`, calculate silver reward by `$i` (place):
  - `$silver = ($i == 1) ? 300 : (($i <= 3) ? 150 : 50);`
  - Call `BlackMarket::addSilver($row['id'], $silver);`
  - Send celebratory notice to user.

**Step 4: Run test to verify it passes**
Run: `docker exec travianz-web php tests/WeeklyMedalsSilverRewardTest.php`
Expected: PASS.

**Step 5: Commit**
```bash
git add GameEngine/Automation/AutomationMedals.php tests/WeeklyMedalsSilverRewardTest.php
git commit -m "feat(silver): award weekly top medal winners with silver bonuses"
```

---

### Task 3: Oasis Nature Bounty Hunter (`OasisBounty.php` & `AutomationBattleResolution.php`)

**Files:**
- Create: `GameEngine/OasisBounty.php`
- Modify: `GameEngine/Automation/AutomationBattleResolution.php`
- Test: `tests/OasisBountyHunterTest.php`

**Step 1: Write the failing test**
Create `tests/OasisBountyHunterTest.php`:
- Verify killing wild animals with hero calculates proper silver bounties:
  - Rat/Spider/Snake/Bat (u31-u34): 1 Silver
  - Boar/Wolf (u35-u36): 2 Silver
  - Bear/Croc/Tiger (u37-u39): 4 Silver
  - Elephant (u40): 8 Silver
- Verify 150 Silver/day cap per player is strictly enforced.

**Step 2: Run test to verify it fails**
Run: `docker exec travianz-web php tests/OasisBountyHunterTest.php`
Expected: FAIL (`OasisBounty` class not found).

**Step 3: Implement minimal code**
- Create `GameEngine/OasisBounty.php` with table schema `oasis_bounty_daily` (`uid`, `bounty_date`, `silver_earned`), calculating rewards and capping at 150/day.
- Hook into `AutomationBattleResolution.php` during nature oasis combat when `$Attacker['uhero'] > 0`.
- Append bounty notice to attack report.

**Step 4: Run test to verify it passes**
Run: `docker exec travianz-web php tests/OasisBountyHunterTest.php`
Expected: PASS.

**Step 5: Commit**
```bash
git add GameEngine/OasisBounty.php GameEngine/Automation/AutomationBattleResolution.php tests/OasisBountyHunterTest.php
git commit -m "feat(silver): implement oasis nature bounty hunter with daily cap"
```

---

### Task 4: Daily Quests Milestone Chest (`DailyQuests.php` & Side Info Widget)

**Files:**
- Create: `GameEngine/DailyQuests.php`
- Create: `Templates/daily_quests.tpl`
- Modify: `dorf1.php`, `dorf2.php` (include in `#side_info`)
- Test: `tests/DailyQuestsTest.php`

**Step 1: Write the failing test**
Create `tests/DailyQuestsTest.php`:
- Verify 4 daily tasks are tracked: Adventure (25 pts), Troop Training (25 pts), Building Upgrades (25 pts), Raids/Attacks (25 pts).
- Verify reaching 100 points enables claim of 50 Silver.
- Verify cannot double-claim on the same calendar day.

**Step 2: Run test to verify it fails**
Run: `docker exec travianz-web php tests/DailyQuestsTest.php`
Expected: FAIL (`DailyQuests` class not found).

**Step 3: Implement minimal code**
- Create `GameEngine/DailyQuests.php` with database table `daily_quests` (`uid`, `quest_date`, `p_adventure`, `p_train`, `p_build`, `p_attack`, `claimed`, `timestamp`).
- Create modern responsive widget `Templates/daily_quests.tpl` showing progress bar, daily checklist, and "Klaim Peti Harian (+50 Silver)".
- Include `Templates/daily_quests.tpl` in `#side_info` of `dorf1.php` and `dorf2.php`.
- Hook progression into `HeroAdventure`, `AutomationBuildQueue` / `Building`, troop training, and battle dispatch.

**Step 4: Run test to verify it passes**
Run: `docker exec travianz-web php tests/DailyQuestsTest.php`
Expected: PASS.

**Step 5: Commit**
```bash
git add GameEngine/DailyQuests.php Templates/daily_quests.tpl dorf1.php dorf2.php tests/DailyQuestsTest.php
git commit -m "feat(silver): add daily quest milestone chest and side info widget"
```

---

### Task 5: Bandit World Boss Bounty Contribution Sharing (`BanditCamp.php`)

**Files:**
- Modify: `GameEngine/BanditCamp.php`
- Test: `tests/BanditCampBountyPoolTest.php`

**Step 1: Write the failing test**
Create `tests/BanditCampBountyPoolTest.php`:
- Verify multiple players attacking a Tier 4 World Boss have their damage logged.
- Verify that when the World Boss is defeated, the 4,000 Silver pool is split proportionally among attackers based on their damage.

**Step 2: Run test to verify it fails**
Run: `docker exec travianz-web php tests/BanditCampBountyPoolTest.php`
Expected: FAIL.

**Step 3: Implement minimal code**
In `GameEngine/BanditCamp.php`:
- Create table `bandit_camp_attacks` (`camp_id`, `uid`, `damage_dealt`, `timestamp`).
- Log damage dealt on every attack against camps.
- In `resolveCampDefeat()` for Tier 4 World Boss, query all unique attackers and distribute 4,000 Silver proportionally with victory notices.
- Retain solitary full reward for single attacker or Tier 1-3 camps.

**Step 4: Run test to verify it passes**
Run: `docker exec travianz-web php tests/BanditCampBountyPoolTest.php`
Expected: PASS.

**Step 5: Commit**
```bash
git add GameEngine/BanditCamp.php tests/BanditCampBountyPoolTest.php
git commit -m "feat(silver): split bandit world boss 4,000 silver pool among attackers"
```

---

### Task 6: Full Integration Testing & Archive Packaging

**Files:**
- Modify: `travianz_patch_update.zip`
- Modify: `travianz_full_public_html.zip`
- Test: Run all test suites across the repository.

**Step 1: Run all test suites**
Run:
```bash
docker exec travianz-web bash -c "php tests/HeroItemPawnSilverTest.php && php tests/WeeklyMedalsSilverRewardTest.php && php tests/OasisBountyHunterTest.php && php tests/DailyQuestsTest.php && php tests/BanditCampBountyPoolTest.php"
```
Expected: PASS across all 5 suites.

**Step 2: Update deployment archives**
Package latest code into `travianz_patch_update.zip` and `travianz_full_public_html.zip`.

**Step 3: Commit**
```bash
git commit -m "feat(silver): complete 5-pillar silver economy and update release archives"
```
