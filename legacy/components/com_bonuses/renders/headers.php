<?php

defined('_JEXEC') or die('Restricted access');
$user = JFactory::getUser();

// Funzione per gestire il plurale
function plural($amount, $singular = '', $plural = 'es') {
    return ($amount == 1) ? $singular : $plural;
}

// Impostazione dei metadati della pagina
$titletag = $set['brand'] . ' Bonuses & Codes (' . $total . ') - ' . date("F Y") . ' - FreeExtraChips';
$descriptiontag = 'Get all bonuses & codes for ' . $set['brand'] . '. This list is regularly updated and as for now it contains ' . $total . ' bonus' . plural($total) . '. Last update ' . date("jS M Y") . '.';
$doc = JFactory::getDocument();
$doc->setTitle($titletag);
$doc->setMetaData('description', $descriptiontag);

// Controllo se la paginazione è la prima pagina
if ($pagination->get_page() == 1) {

    // Recupera il valore di "cid" dalla richiesta (GET o POST)
    $cid = JFactory::getApplication()->input->getInt('cid');

    // Switch-case in base al valore di cid
    switch ($cid) {
        case 1099:
            // Codice specifico per cid = 1
            echo '<div class="rt-block">
            <div id="rt-content-top">

  <!-- 1. Heading -->
  <h1>Ozwin Casino Bonuses: A Complete Guide</h1>

  <!-- 1. Paragraph -->
  <p>Looking for Ozwin casino bonuses? Ozwin Casino is a popular online casino that accepts players from Australia and has developed a strong reputation since opening in May 2020. As a relatively new online casino, Ozwin is always looking to attract new customers, and one of the main ways that this is achieved is with an extensive collection of bonuses.</p>

  <p>Here at <a href="https://www.freeextrachips.com" title="FreeExtraChips">FreeExtraChips</a>, we have collated 342+ exciting Ozwin bonuses for you to choose from, each meticulously categorized to showcase the most recent and relevant promotions. Our dedicated team has verified each bonus for accuracy and fairness, so you can be sure that all Ozwin bonus codes listed here will work. Browse the Ozwin Casino bonus codes below or keep reading to learn more about Ozwin Casino and the kind of bonuses that you can take advantage of.</p>

  <!-- 2. Heading -->
  <h2>Ozwin Casino Overview</h2>

  <!-- 2. Paragraph -->
  <p>Despite being relatively new, Ozwin Casino has emerged as a popular choice in recent times, particularly in Australia. The casino is powered by Realtime Gaming, with all of the games com-ing from this provider, including a mix of new and popular online slots and some bigger jackpot slots, including Aztec’s Millions and Megasaur. You can also enjoy classic table games like baccarat, poker, roulette, and much more. The casino has a user-friendly interface with mobile gaming supported, a range of payment options available, and a minimum deposit of just $10. Check out our review of Ozwin to learn more. We also have other <a href="https://www.freeextrachips.com/online-casinos" title="Casino Reviews">casino reviews</a> to check out, including <a href="https://www.freeextrachips.com/online-casinos/banking/bitcoin/" title="Bitcoin Casinos">Bitcoin casinos</a>.</p>

  <!-- 3. Heading -->
  <h2>Types of Bonuses Available at Ozwin Casino</h2>

  <!-- 3. Paragraph -->
  <p>Ozwin offers a wide range of bonuses that cater to all preferences. Here are a few of the main types of Ozwin Casino offers you can take advantage of:</p>

  <!-- 3.1 Subheading -->
  <h3>Welcome Bonus</h3>
  <p>Welcome bonuses are one of the most effective ways for an online casino to attract new custom-ers. The exact details of the welcome bonus can change, but it typically includes a percentage match bonus and free spins on selected slot games. This means that when you make an initial deposit, Ozwin will match it up to a certain amount. This is an appealing bonus because it gives you free money to play with, which can extend your playing time and improve your chances of winning real money.</p>

  <!-- 3.2 Subheading -->
  <h3>No Deposit Bonus</h3>
  <p>Ozwin no deposit bonus promotions involve gifting players either free spins or a cash bonus without having to make a deposit themselves. For example, an Ozwin casino $50 no deposit bo-nus would allow players to enjoy $50 for free without having to make a deposit and play with their own money. However, keep in mind that there will be wagering requirements involved (more on this to come). Ozwin no deposit bonus promotions are a great way for new and existing players to enjoy risk-free gaming.</p>

  <!-- 3.3 Subheading -->
  <h3>Match Deposit Bonus</h3>
  <p>Match deposit bonus is a promotion that is typically included in a welcome package, but they can also be available to existing players. These promotions are often updated regularly and may be tied to specific casino games, so be sure to check back frequently to discover the latest Ozwin bonus codes.</p>

  <!-- 3.4 Subheading -->
  <h3>Free Spins</h3>
  <p>Free spins are another common form of promotion that is often included in a welcome package but also used as a standalone promotion, sometimes linked to one particular game. For example, there might be an Ozwin Casino 100 free spins promotion where players can enjoy 100 free spins on one game. Free spins are popular as they allow players to enjoy the excitement and thrill of a slot game without having to put their own money at risk and have the potential of winning real money, but there will be wagering requirements involved before you can make a withdrawal. Ozwin Casino free spins are updated regularly, so be sure to check back often.</p>

  <!-- 3.5 Subheading -->
  <h3>Cashback Offers</h3>
  <p>Cashback deals are also regularly available at Ozwin. This involves players receiving a percent-age of their losses back, helping players to extend gameplay and minimize financial risk.</p>

  <!-- 3.6 Subheading -->
  <h3>Loyalty Programs &amp; VIP Rewards</h3>
  <p>Ozwin also aims to retain its customers with a multi-level loyalty program that allows dedicated players to enjoy a range of perks. The player levels include:</p>
  <ul>
    <li><strong>Tourist:</strong> 25% cashback and a daily bonus</li>
    <li><strong>Pathfinder:</strong> 30% cashback and a medium daily bonus</li>
    <li><strong>Adventurer:</strong> 24/7 priority support, 35% cashback, and a medium-high daily bonus</li>
    <li><strong>Expert:</strong> 24/7 priority support, 40% cash back, and a high daily bonus</li>
    <li><strong>Hero:</strong> 24/7 premium support, 45% cash back, and a super daily bonus</li>
    <li><strong>Absolute Legend:</strong> 24/7 premium support, personal VIP host, 50% cashback, monster daily bonus</li>
  </ul>

  <!-- 4. Heading -->
  <h2>How to Claim Ozwin Casino Bonus Offers</h2>
  <p>Claiming Ozwin Casino bonus offers is generally a simple and straightforward process with a few basic steps involved:</p>

  <ol>
    <li><strong>Find Bonuses:</strong> First, you need to find a bonus. You can do this by bookmarking this page and checking back regularly. We are constantly updating the list, which means that you can find all of the very latest bonuses and promotions here, such as the latest Ozwin Casino no deposit bonus. Simply click on the bonus, and we will provide all of the information that you need to know about the promotion.</li>
    <li><strong>Read the T’s &amp; C’s:</strong> It might not be the most exciting part, but it is important that you read all of the information about the promotion. This should involve looking at the wagering requirements, expiry dates, maximum cashout, and expiration date.</li>
    <li><strong>Enter Bonus Codes:</strong> Once you arrive at the Ozwin website, you will typically need to enter the bonus code into the box provided (if required). If it is an Ozwin Casino no deposit bonus, you can now start to enjoy the promotion.</li>
    <li><strong>Make a Deposit:</strong> If required, you can then make a deposit - just make sure that you meet the minimum deposit requirement for the promotion.</li>
    <li><strong>Start Playing:</strong> Once credited, you can take advantage of the bonus. Just make sure that you ad-here to the terms and conditions and understand the wagering requirements.</li>
  </ol>

  <!-- 5. Heading -->
  <h2>Expert Tips for Maximizing Your Ozwin Casino Bonuses</h2>
  <p>So, what can you do to maximize your Ozwin bonus codes? There is no way to guarantee winning when it comes to online casino games, but there are a few tips to be aware of that will help you maximize the promotion and potentially help you win real money.</p>

  <!-- 5.1 Subheading -->
  <h3>Pick Slots with a High RTP Rate</h3>
  <p>If you have an Ozwin Casino 100 free spins bonus, it is smart to check the RTP (return to player) rate for each eligible game. Essentially, this refers to the amount of money a player can expect to get back (on average). So, if the game has an RTP rate of 98%, players will get, on average, $98 of every $100 wagered. By picking a game with a high RTP rate, you improve your chances of win-ning.</p>

  <!-- 5.2 Subheading -->
  <h3>Understand the Wagering Requirements</h3>
  <p>Wagering requirements have been mentioned above, and one of the most important aspects to understand when it comes to Ozwin Casino no deposit bonus codes. Essentially, this refers to how many times you must bet your bonus funds before they can be withdrawn as cash. So, if a promo-tion has a wagering requirement of 30X, you would have to bet the bonus amount 30 times over before you can withdraw it as cash. This is also known as the playthrough rate. Generally, wager-ing requirements of 30x or 40x are deemed fair.</p>

  <!-- 5.3 Subheading -->
  <h3>Check the Withdrawal Limits</h3>
  <p>Some promotions have cashout restrictions, so be sure to check how much you can withdraw be-fore getting started.</p>

  <!-- 5.4 Subheading -->
  <h3>Check the Time Limits</h3>
  <p>Many promotions also have a time limit. For example, Ozwin Casino 100 free spins may only be available for one week, so you want to make sure that you use all of your free spins within this timeframe.</p>

  <!-- 6. Heading -->
  <h2>FAQs</h2>
  <p>Here are a few of the most commonly asked questions about Ozwin bonuses:</p>

  <div itemscope itemtype="http://schema.org/FAQPage">

    <div itemprop="mainEntity" itemscope itemtype="http://schema.org/Question">
      <h3><span itemprop="name">Can I Sign Up for Multiple Welcome Bonuses?</span></h3>
      <div itemprop="acceptedAnswer" itemscope itemtype="http://schema.org/Answer">
        <ul itemprop="text">
          <li>No, you should never create more than one account with a casino, as this can result in a ban.</li>
        </ul>
      </div>
    </div>

    <div itemprop="mainEntity" itemscope itemtype="http://schema.org/Question">
      <h3><span itemprop="name">Are There Bonuses for Existing Players?</span></h3>
      <div itemprop="acceptedAnswer" itemscope itemtype="http://schema.org/Answer">
        <ul itemprop="text">
          <li>Yes, Ozwin has many different types of bonuses for existing players, including Ozwin no deposit bonus existing players promotions, and free spins.</li>
        </ul>
      </div>
    </div>

    <div itemprop="mainEntity" itemscope itemtype="http://schema.org/Question">
      <h3><span itemprop="name">How Will I Know When I&apos;ve Met the Wagering Requirements?</span></h3>
      <div itemprop="acceptedAnswer" itemscope itemtype="http://schema.org/Answer">
        <ul itemprop="text">
          <li>Track your progress in your account details on the website or contact customer support for more information.</li>
        </ul>
      </div>
    </div>

    <div itemprop="mainEntity" itemscope itemtype="http://schema.org/Question">
      <h3><span itemprop="name">Can I Combine an Ozwin Casino No Deposit Bonus with Other Promotions?</span></h3>
      <div itemprop="acceptedAnswer" itemscope itemtype="http://schema.org/Answer">
        <ul itemprop="text">
          <li>Typically, casinos online allow one active bonus at a time, so you will need to complete or forfeit your Ozwin Casino $50 no deposit bonus before claiming another promotion.</li>
        </ul>
      </div>
    </div>

  </div>

  <!-- 7. Heading -->
  <h2>Get Started with Ozwin Bonuses Today</h2>

  <!-- 7. Paragraph -->
  <p>Now you will be ready to take advantage of the best and latest bonuses from Ozwin. Whether you are taking advantage of a generous new customer promotion or the newest Ozwin Casino no deposit bonus '.date("Y").', these promotions can be a great way to extend your playing time, enhance your gaming experience, and potentially win real money.</p>

  <p>Ozwin regularly updates its promotions, so be sure to check back here regularly to find all of the latest Ozwin Casino offers to take advantage of, including an Ozwin Casino 50 no deposit bonus.</p>
</div>
</div>
';
            break;
        case 1096:
            // Codice specifico per cid = 2
            echo '<div class="rt-block">
            <div id="rt-content-top">


  <!-- 1. Heading -->
  <h1>The Complete Guide To Yabby Casino Bonuses</h1>

  <!-- 1. Paragraph -->
  <p>On the lookout for Yabby Casino bonus codes? Yabby Casino was established in 2020 but has quickly become a key player on the scene thanks to its large library of games, acceptance of players from all over the globe, and its generous bonuses and promotions. Yabby Casino frequently updates its promotions for both new and existing customers, so you will want to be on the ball and aware of all the best and latest bonuses.</p>

  <!-- 2. Paragraph -->
  <p>Here at <a href="https://www.freeextrachips.com" title="FreeExtraChips">FreeExtraChips</a> we have done all the hard work for you by collecting the promotions, including the latest Yabby Casino no deposit bonus promotions, Yabby Casino sign-up bonus promotions, and Yabby Casino free spins. If you are familiar with Yabby Casino and the promotions then dive into the latest bonuses now, but if you want to learn more, be sure to keep reading for everything you need to know about this fantastic casino and its generous bonuses.</p>

  <!-- 3. Heading -->
  <h2>Yabby Casino Overview</h2>

  <!-- 3. Paragraph -->
  <p>Yabby Casino was established in 2020 and is powered by Realtime Gaming. The casino accepts players from Australia, New Zealand, the US, and other places all over the world in both fiat and cryptocurrency. The casino has a huge selection of games to choose from, including the most popular and latest RTG slots. Additionally, there are a few branded slots, such as The Big Bopper and Ritchie Valens, as well as titles with progressive jackpots, such as Megasaur. They also have plenty of single and multi-hand video pokers, blackjack, baccarat, roulette, and more.</p>

  <!-- 4. Paragraph -->
  <p>Deposits and withdrawals are free of charge, with the minimum deposit being $10 and withdrawals $20. This is also one of the best <a href="https://www.freeextrachips.com/online-casinos/banking/bitcoin/" title="Bitcoin Casinos">Bitcoin casinos</a>, as it accepts Bitcoin, Ethereum, Bitcoin Cash, Litecoin, Dogecoin, Ripple, Monero, and Dash. Check out our review of Yabby Casino here - we also have other <a href="https://www.freeextrachips.com/online-casinos" title="Casino Reviews">casino reviews</a> to check out for other places to play.</p>

  <!-- 5. Heading -->
  <h2>Yabby Casino Bonuses Explained</h2>

  <!-- 5. Paragraph -->
  <p>Yabby Casino is known for its generous bonuses for both new and existing players. Promotions are updated regularly, so be sure to check back here often to take advantage of the latest bonuses. The main types of bonuses you will find at this casino include:</p>

  <!-- 6. Subheading -->
  <h3>Yabby Casino Sign-Up Bonus</h3>
  <p>Sign-up bonuses are common in the online casino world as a way to entice new customers. The Yabby Casino sign-up bonus typically includes matched deposits and free spins. This means that players can get the amount of money that they deposit matched by the casino up to a certain amount, essentially providing the customer with free money to extend their playtime. Free spins are also often included, which could be available to use on all slot games, a select few, or just one.</p>

  <!-- 7. Subheading -->
  <h3>Yabby Casino No Deposit Bonus</h3>
  <p>The Yabby Casino no deposit bonus is a promotion that involves gifting the player free cash or spins without having to make a deposit, such as a Yabby Casino $225 no deposit bonus. This can be for both new and existing players and is a great way for players to enjoy online casino games without having to spend and risk their own money. There are often restrictions in place, such as wagering requirements (more on this to come).</p>

  <!-- 8. Subheading -->
  <h3>Yabby Casino Deposit Match Bonus</h3>
  <p>Unlike the Yabby Casino no deposit bonus, a deposit match bonus requires players to deposit their own funds with the casino matching a percentage of this (often 100%) up to a certain amount. As mentioned above, this is often included in the Yabby sign-up bonus but can also be available to existing players.</p>

  <!-- 9. Subheading -->
  <h3>Yabby Casino Free Slot Spins</h3>
  <p>As the name suggests, Yabby Casino free slot spins is a promotion that gifts players a certain amount of free spins to use on their online slot games, such as 50 free spins. This could be used on any slot game, a select few, or just one. Free spins or chips are often part of the Yabby Casino sign-up bonus, but they are also often offered to existing players. Free chips are a great bonus as they allow players to enjoy the excitement of slot games without using their own money and can be used to win real money (provided you meet the wagering requirements and other criteria).</p>

  <!-- 10. Subheading -->
  <h3>VIP Bonuses</h3>
  <p>Yabby also features a VIP program open to all players who deposit a certain amount or collect enough comp points. The higher you are on the VIP scale, the better the benefits and bonuses.</p>

  <!-- 11. Heading -->
  <h2>Understanding the Ts &amp; Cs</h2>
  <p>It is crucial that you take the time to read the terms and conditions of any Yabby casino no deposit or match deposit promotion. There are a few key areas to focus on, including:</p>

  <!-- 12. Subheading -->
  <h3>Wagering Requirements</h3>
  <p>Wagering requirements are perhaps the most important area to consider with casino promotions. Essentially, this refers to the number of times you need to bet your bonus funds before they can be withdrawn as cash. For instance, a 40x wagering requirement would mean that you have to gamble the bonus amount 40 times before you can pay out any wins. Therefore, you should always check the wagering requirements before signing up for the Yabby Casino no deposit bonus.</p>

  <!-- 13. Subheading -->
  <h3>Max Bet Per Hand</h3>
  <p>There may also be a maximum bet per hand that you can make, so you need to find out what this is before you start playing.</p>

  <!-- 14. Subheading -->
  <h3>Minimum Deposit &amp; Maximum Cashout</h3>
  <p>Promotions may also have a minimum deposit to qualify for the promotion and a maximum cashout limit, which is the maximum amount you can withdraw after meeting the wagering requirements.</p>

  <!-- 15. Subheading -->
  <h3>Eligible Games</h3>
  <p>Often, Yabby Casino no deposit bonus codes and free spins can only be used on certain games. Always check what games are eligible first.</p>

  <!-- 16. Subheading -->
  <h3>Expiry Dates</h3>
  <p>Some bonuses will also have an expiry date, so you will need to use all funds or free spins before this date to avoid losing them.</p>

  <!-- 17. Heading -->
  <h2>How to Claim Yabby Casino Bonus Codes</h2>
  <p>Claiming a Yabby Casino bonus is quick and easy, especially when you use this page to find the latest promotions! Here are the main steps involved:</p>

  <ul>
    <li><strong>Find promotions:</strong> First, you need to find promotions. We have all of the latest bonuses here, such as the Yabby Casino $225 no deposit bonus, and regularly update the page, so be sure to check back often. Click on the promotion, and we will provide all of the information you need, including codes and wagering requirements. Click “Get bonus,” and you will be taken to the relevant page.</li>
    <li><strong>Sign up:</strong> If you are a new customer, you will need to sign up with Yabby Casino. This is a quick and easy process, but be sure to check if you are in one of the restricted countries.</li>
    <li><strong>Make a Deposit (if required):</strong> If it is a deposit match bonus, you will then need to enter a deposit to qualify – make sure that you know the minimum deposit for the promotion.</li>
    <li><strong>Enter the Bonus Code (if required):</strong> Many promotions require a deposit bonus code. We will provide you with the Yabby Casino bonus codes, so you simply need to enter the Yabby discount code into the box provided.</li>
    <li><strong>Start Playing:</strong> Once you have entered your Yabby Casino no deposit bonus codes, made a deposit, or claimed your free spins, you can start playing. Just make sure that you have read the Ts and Cs and understand the wagering requirements.</li>
  </ul>

  <!-- 18. Heading -->
  <h2>Yabby Casino Bonus Tips</h2>
  <p>Here are a few tips to maximize your Yabby Casino bonuses:</p>
  <ul>
    <li><strong>Look for Low Wagering Requirements:</strong> Low wagering requirements will improve your chances of winning real money, so look for bonuses with requirements of 30x or 40x.</li>
    <li><strong>Pick High RTP Games:</strong> Games with a high RTP (return to player) rate will be more likely to result in wins, but there are no guarantees.</li>
    <li><strong>Check Back Regularly:</strong> There are constantly new promotions being made available, so bookmark this page and check back regularly.</li>
  </ul>

  <!-- 19. Heading -->
  <h2>FAQs: Your Questions Answered</h2>
  <p>Here are a few questions we often get asked about Yabby Casino bonuses:</p>

  <div itemscope itemtype="http://schema.org/FAQPage">

    <div itemprop="mainEntity" itemscope itemtype="http://schema.org/Question">
      <h3><span itemprop="name">Can I Claim Multiple Bonuses?</span></h3>
      <div itemprop="acceptedAnswer" itemscope itemtype="http://schema.org/Answer">
        <ul itemprop="text">
          <li>Typically, you can only claim one bonus at a time, so you will need to either fulfill or forfeit your Yabby no deposit bonus before signing up for another.</li>
        </ul>
      </div>
    </div>

    <div itemprop="mainEntity" itemscope itemtype="http://schema.org/Question">
      <h3><span itemprop="name">Can I Withdraw Bonus Winnings Immediately?</span></h3>
      <div itemprop="acceptedAnswer" itemscope itemtype="http://schema.org/Answer">
        <ul itemprop="text">
          <li>No, you will need to meet wagering requirements first.</li>
        </ul>
      </div>
    </div>

    <div itemprop="mainEntity" itemscope itemtype="http://schema.org/Question">
      <h3><span itemprop="name">How Can I Contact Yabby Casino?</span></h3>
      <div itemprop="acceptedAnswer" itemscope itemtype="http://schema.org/Answer">
        <ul itemprop="text">
          <li>Yabby Casino offers a few customer support options, including live chat, email, and phone.</li>
        </ul>
      </div>
    </div>

    <div itemprop="mainEntity" itemscope itemtype="http://schema.org/Question">
      <h3><span itemprop="name">How Often Are No Deposit Bonuses Updated?</span></h3>
      <div itemprop="acceptedAnswer" itemscope itemtype="http://schema.org/Answer">
        <ul itemprop="text">
          <li>Bonuses are updated frequently, so be sure to visit this page to discover the newest promotions, such as a Yabby Casino $100 no deposit bonus.</li>
        </ul>
      </div>
    </div>

  </div>

  <!-- 20. Heading -->
  <h2>Take Advantage Of Yabby Casino Bonuses Today</h2>

  <!-- 20. Paragraph -->
  <p>Now, you will be ready to dive in and take advantage of the latest and best Yabby no deposit bonus codes. We have all of the newest promotions listed on this page, but remember to check back regularly as we make frequent updates to the page.</p>

  <p>Yabby Casino offers generous promotions for both new and existing players and accepts players from all over the world. With an extensive library of games to choose from, you can enjoy various bonuses that will extend your playtime, improve your chances of winning, and give you the best online casino experience.</p>


</div>
</div>';
            break;
        // Puoi aggiungere altri case se necessario
        default:
            // Caso default: visualizzazione base per la pagina 1
            echo '<section id="rt-content-top" class="item-page">
            <h1 class="tcontainer">' . $set['brand'] . ' Bonuses & Codes</h1>
            <p>Explore an extensive range of bonuses available at <a href="' . $reviewurl . '">' . $set['brand'] . '</a>. Our curated selection features ' . $total . ' exciting bonuses, each meticulously categorized to showcase the most recent and relevant promotions. Our dedicated team thoroughly verifies each bonus for accuracy and fairness before it is approved and listed. This ensures that you have access to only the best offers. Browse our page to find your perfect bonus or read our comprehensive <a href="' . $reviewurl . '">' . $set['brand'] . ' review</a> for more insights.</p>
            
            <p>' . $set['brand'] . ' ' . $brandcountrytxt . '. Whether you’re looking for <a href="https://www.freeextrachips.com/bonuses/no-deposit-casinos" title="No deposit Bonuses">no deposit bonuses</a>, free spins, or match bonuses, our list has something for everyone. Each bonus is not only verified but also regularly updated to reflect the latest offers. Our commitment to quality means that you can trust the information provided.</p>
            
            <p>Stay informed with our up-to-date listings, diligently checked and refreshed on <b>' . date("jS M Y") . '</b>, ensuring you have access to the freshest and most lucrative offers available. Dive into our detailed bonus descriptions and find out which promotions are the best fit for your gaming style. ' . $set['brand'] . ' is your go-to destination for the most rewarding casino experience.</p>
            </section>';
            break;
    }

} elseif ($pagination->get_page() > 1) {
    // Visualizzazione per pagine successive (paginazione > 1)
    echo '<div id="rt-content-top">
    <h1 class="tcontainer">' . $set['brand'] . ' Bonuses - <span class="page">Page ' . $pagination->get_page() . ' of ' . $pagination->get_pages() . '</span></h1>
    <p>Viewing ' . (empty($type) ? 'Casino Bonuses' : $labels[$type]) . ' from ' . ($getpage + 1) . ' to ' . min($total, $pagination->get_page() * $records_per_page) . '.</p>
    </div>';
}

// Codice aggiuntivo per utenti con id specifico
if ($user->id == '42') {
    foreach ($rowcount as $key) {
        $newarr[$key['bid']] = array($key['type'] => $key['status']);
    }

    echo '<pre>';
    foreach ($bonustype as $val => $name) {
        $countact = array_count_values(array_column($newarr, $val))['active'];
        $countexp = array_count_values(array_column($newarr, $val))['expired'];
        $counttot = array_count_values(array_column($rowcount, 'type'))[$val];
        echo $name . ' => ' . $counttot . ' (Active: ' . $countact . ', Expired: ' . $countexp . ')<br>';
    }
    echo '</pre>';
}
?>
