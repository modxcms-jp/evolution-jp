<h2 class="tab"><?= lang('profile') ?></h2>
<table class="settings">
    <tr>
        <th><?= lang('user_full_name') ?>:</th>
        <td><input
                name="fullname"
                value="<?= hsc(user('fullname', '')) ?>"
                type="text"
                class="inputBox"
            /></td>
    </tr>
    <tr>
        <th><?= lang('user_phone') ?>:</th>
        <td><input
                name="phone"
                value="<?= hsc(user('phone')) ?>"
                type="text"
                class="inputBox"
            /></td>
    </tr>
    <tr>
        <th><?= lang('user_mobile') ?>:</th>
        <td><input
                name="mobilephone"
                value="<?= hsc(user('mobilephone')) ?>"
                type="text"
                class="inputBox"
            /></td>
    </tr>
    <tr>
        <th><?= lang('user_fax') ?>:</th>
        <td><input
                name="fax"
                value="<?= hsc(user('fax')) ?>"
                type="text"
                class="inputBox"
            /></td>
    </tr>
    <tr>
        <th><?= lang('user_street') ?>:</th>
        <td><input
                name="street"
                value="<?= hsc(user('street')) ?>"
                type="text"
                class="inputBox"
                onchange="documentDirty=true;"
            /></td>
    </tr>
    <tr>
        <th><?= lang('user_city') ?>:</th>
        <td><input
                name="city"
                value="<?= hsc(user('city')) ?>"
                type="text"
                class="inputBox"
                onchange="documentDirty=true;"
            /></td>
    </tr>

    <tr>
        <th><?= lang('user_state') ?>:</th>
        <td><input
                name="state"
                value="<?= hsc(user('state')) ?>"
                type="text"
                class="inputBox"
            /></td>
    </tr>
    <tr>
        <th><?= lang('user_zip') ?>:</th>
        <td><input
                name="zip"
                value="<?= hsc(user('zip')) ?>"
                type="text"
                class="inputBox"
            /></td>
    </tr>
    <tr>
        <th><?= lang('user_country') ?>:</th>
        <td>
            <select size="1" name="country" class="inputBox">
                <?php $chosenCountry = postv('country', user('country')); ?>
                <option
                    value="" <?= selected(empty($chosenCountry)) ?>
                >&nbsp;
                </option>
                <?php
                foreach ($_country_lang as $key => $country) {
                    echo sprintf(
                        '<option value="%s"%s>%s</option>',
                        $key,
                        selected(isset($chosenCountry) && $chosenCountry == $key),
                        $country
                    );
                }
                ?>
            </select>
        </td>
    </tr>
    <tr>
        <th><?= lang('user_dob') ?>:</th>
        <td>
            <input
                name="dob"
                value="<?php echo(user('dob') ? evo()->toDateFormat(user('dob'), 'dateOnly') : ''); ?>"
                type="text"
                id="dob"
                class="DatePicker"
                onblur="documentDirty=true;"
            >
            <a
                onclick="document.userform.dob.value=''; return true;"
                style="cursor:pointer; cursor:hand"
            ><img
                    src="media/style/<?= evo()->config('manager_theme') ?>/images/icons/cal_nodate.gif"
                    align="absmiddle"
                    border="0"
                    alt="<?= lang('remove_date') ?>"
                ></a>
        </td>
    </tr>
    <tr>
        <th><?= lang('user_gender') ?>:</th>
        <td><select name="gender" class="inputBox">
                <option value="0"></option>
                <option
                    value="1"
                    <?= selected(user('gender') == 1) ?>
                ><?= lang('user_male') ?></option>
                <option
                    value="2"
                    <?= selected(user('gender') == 2) ?>
                ><?= lang('user_female') ?></option>
                <option
                    value="3"
                    <?= selected(user('gender') == 3) ?>
                ><?= lang('user_other') ?></option>
            </select>
        </td>
    </tr>
    <tr>
        <th valign="top"><?= lang('comment') ?>:</th>
        <td>
            <textarea
                name="comment"
                type="text"
                class="inputBox"
                rows="5"
            ><?= hsc(user('comment')) ?></textarea>
        </td>
    </tr>
    <tr>
        <th><?= lang('user_photo') ?></th>
        <td><input
                name="photo"
                value="<?= hsc(user('photo')) ?>"
                type="text"
                maxlength="255"
                style="width: 150px;"
            />
            <input
                value="<?= lang('insert') ?>"
                type="button"
                onclick="BrowseServer();"
            />
            <div><?= lang('user_photo_message') ?></div>
            <div>
                <?php
                if (postv('photo')) {
                    $photo = postv('photo');
                } elseif (user('photo')) {
                    $photo = user('photo');
                } else {
                    $photo = MODX_BASE_URL . 'manager/' . style('tx');
                }

                if (strpos($photo, '/') !== 0 && !preg_match('@^https?://@', $photo)) {
                    $photo = MODX_BASE_URL . $photo;
                }
                ?>
                <img name="iphoto" src="<?= $photo ?>"/>
            </div>
        </td>
    </tr>
</table>
