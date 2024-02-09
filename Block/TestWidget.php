<?php

namespace Iwoca\Iwocapay\Block;

use Magento\Widget\Block\BlockInterface;
use Magento\Framework\View\Element\Template;

class TestWidget extends Template implements BlockInterface
{
    protected $_template = "widget/test_widget.phtml";
}